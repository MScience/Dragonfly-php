<?php

namespace MScience{

    interface ISmsClient
    {
        public function send(array $messages);

        public function getMessageStatus(array $messages);

        public function getInboundStatus($address);

        public function registerInbound(array $addresses);

        public function removeInbound(array $addresses);

        public function clearInbound();

        public function getInboundMessages();

        public function getDeliveryReceipts();
    }

    class Result
    {
        public $Code;
        public $SubCode;

        public function HasError(){ return strtoupper($this->Code) !="OK"; }
        public function ErrorMessage() {
            if($this->HasError()){return $this->SubCode;}else{return ''; }
        }

        private function __construct($code, $subCode)
        {
            $this->Code = $code;
            $this->SubCode = $subCode;
        }

        public static function fromString($result)
        {
            $results = array();
            if (empty($result))
            {
                throw new \InvalidArgumentException("A valid result string must be provided");
            }

            $resultParts = explode(',', $result);

            foreach($resultParts as $value){
                if(!empty($value)){
                    $resParts = explode('-',$value);
                    array_push($results, new Result($resParts[0],$resParts[1]));
                }
            }

            return $results;
        }

    }

    class SmsMessage{
        public $Source;
        public $Destination;
        public $SourceId;
        public $Text;
        public $DeliveryReport;

        public function __construct($source, $destination, $sourceId, $text, $deliveryReport){
            $this->Source = $source;
            $this->Destination = $destination;
            $this->SourceId = $sourceId;
            $this->Text = $text;
            $this->DeliveryReport = $deliveryReport;
        }
    }
    
    class SendResult
    {
        public $Code;
        public $MessageId;
        public $MessageBalance;
        public $PendingMessages;
        public $SurchargeBalance;
        public $ErrorMessage;

        public function HasError() { 
            $msg = trim(ErrorMessage);
            return !empty($msg); 
        }

        private function __construct($code, $messageId, $messageBalance, $pendingMessages, $surchargeBalance, $errorMessage = '')
        {
            $this->Code = $code;
            $this->MessageId = $messageId;
            $this->MessageBalance = $messageBalance;
            $this->PendingMessages = $pendingMessages;
            $this->SurchargeBalance = $surchargeBalance;
            $this->ErrorMessage = $errorMessage;
        }

        public static function fromString($input)
        {
            $results = array();

            if (empty($input))
            {
                return $results;
            }

            $inputParts = explode(',,',$input);

            foreach ($inputParts as $singleResult)
            {
                $resultParts = explode(',',$singleResult);
                if (strpos($resultParts[0], 'OK') !== false)
                {
                    $successParts = explode(',',$resultParts[0]);
                    $statusIdPart = explode('-',$successParts[0]);
                    
                    $messageId = (int)$statusIdPart[1];
                    $messageBalance = (int)$resultParts[1];
                    $pendingMessages = (int)$resultParts[2];
                    $surchargeBalance = (float)$resultParts[3];

                    array_push($results, new SendResult($resultParts[0], $messageId, $messageBalance, $pendingMessages, $surchargeBalance));
                }
                else
                {
                    $errorCode = explode('-',$resultParts);
                    $results[] = (new SendResult($resultParts[0], 0, 0, 0, 0, $errorCode[1]));
                }

            }

            return $results;
        }
    }
    

    class SmsClient implements ISmsClient
    {
        private $accountId;
        private $password;
        private $serviceUri;
        private $backupUri;
        private $defaultNamespace = 'http://www.m-science.com/MScienceSMSWebService/';

        public function __construct($accountId, $password, $serviceUri = 'http://smswebservice1.m-science.com/MScienceSMSWebService.asmx', $backupUri = 'http://smswebservice2.m-science.com/MScienceSMSWebService.asmx')
        {
            $this->accountId = $this->EncryptString($accountId);
            $this->password = $this->EncryptString($password);
            $this->serviceUri = $serviceUri;
            $this->backupUri = $backupUri;
        }
        /**
         * Summary of send
         * @param SmsMessage[] $messages 
         * @return SendResult[]
         */
        public function send(array $messages){
            $sourceTags = array();
            $sourceAddresses = array();
            $deliveryReceipts = array();
            $productMessageIds = array();
            $destinations = array();
            $messagesText = array();

            foreach ($messages as $message){
                array_push($sourceTags,'Php Api');
                array_push($sourceAddresses, $message->Source);
                
                if($message->DeliveryReport){
                    array_push($deliveryReceipts, 1);
                }
                else{
                    array_push($deliveryReceipts, 0);
                }
                array_push($productMessageIds, $message->SourceId);
                array_push($destinations, $message->Destination);
                array_push($messagesText, $message->Text);
            }

            $url = $this->serviceUri;
            
            while(true){
                try{
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);
                    
                    $result = $client->SendMessages(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password, 'sourceTag'=> $sourceTags, 'sourceAddress' => $sourceAddresses, 'deliveryReceipt' => $deliveryReceipts, 'productMessageID' => $productMessageIds, 'destination' => $destinations, 'message' => $messagesText));

                    return SendResult::fromString($result->SendMessagesResult);
                    
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
            }
        }

        /**
         * Summary of getMessageStatus
         * @param array $messages 
         * @return Result[]
         */
        public function getMessageStatus(array $messages){
            $messageIds = array();
            
            foreach ($messages as $message){
                array_push($messageIds, $message);
            }

            $url = $this->serviceUri;
            
            while(true){
                try{
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);
                    
                    $result = $client->GetMessageStatus(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password, 'messageID' => $messageIds));

                    return Result::fromString($result->GetMessageStatusResult);
                }
                catch(\SoapFault $exception){
                    $url = $this->backupUri;
                }
            }
        }

        public function getInboundStatus($address){
            $url = $this->serviceUri;
            
            while(true){
                try
                {
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);
                    
                    $result = $client->GetURLStatus(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password, 'address' => $address));
                    
                    return Result::fromString($result->GetURLStatusResult)[0];
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
            }
        }

        public function registerInbound(array $addresses){
            $url = $this->serviceUri;
            
            while (true)
            {
            	try
                {
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);

                    $result = $client->RegisterForInbound(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password, 'address' => $addresses));
                    
                    return Result::fromString($result->RegisterForInboundResult);
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
            }
        }

        public function removeInbound(array $addresses){
            $url = $this->serviceUri;
            
            while (true)
            {
            	try
                {
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);

                    $result = $client->UnregisterInboundAddress(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password, 'address' => $addresses));
                    
                    return Result::fromString($result->UnregisterInboundAddressResult);
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
                
            }
            
        }

        public function clearInbound(){
            $url = $this->serviceUri;
            
            while (true)
            {
            	try
                {
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);
                    
                    $result = $client->ResetInboundCallbacks(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password));
                    
                    return Result::fromString($result->ResetInboundCallbacksResult)[0];
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
                
            }

        }

        public function getInboundMessages(){
            $results = $this->getInboundMessagesInternal();

            $filteredResults = array();
            $ackMessages = array();
            foreach ($results as $message)
            {
            	if(!$message->DeliveryReceipt){
                    array_push($filteredResults, $message);
                    array_push($ackMessages, $message->Id);
                }
            }
            
            if(!empty($ackMessages)){
                $this->ackMessages($ackMessages);
            }
            
            return $filteredResults;
        }

        public function getDeliveryReceipts(){

            $results = $this->getInboundMessagesInternal();
            
            $filteredResults = array();
            $ackMessages = array();
            foreach ($results as $message)
            {
            	if($message->DeliveryReceipt){
                    array_push($filteredResults, $message);
                    array_push($ackMessages, $message->Id);
                }
            }
            
            if(!empty($ackMessages)){
                $this->ackMessages($ackMessages);
            }
            
            return $filteredResults;
        }
        
        private function getInboundMessagesInternal(){
            $url = $this->serviceUri;
            
            while (true)
            {
            	try
                {
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);
                    
                    $result = $client->GetNextInboundMessages(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password));
                    
                    return InboundMessageResult::fromString($result->GetNextInboundMessagesResult);        
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
                
            }

        }

        private function ackMessages($ackMessages){
            $url = $this->serviceUri;
            
            while (true)
            {
            	try
                {
                    $wsdl = $url . '?wsdl';
                    $client = new \SoapClient($wsdl);

                    $result = $client->AckMessages(array('encrypt'=> true, 'accountID'=> $this->accountId, 'password'=> $this->password, 'messageID' => $ackMessages));        
                    
                    return Result::fromString($result->AckMessagesResult);
                }
                catch (\SoapFault $exception)
                {
                    $url = $this->backupUri;
                }
            }
        }
        
        private function EncryptString($value){
            $output = "";
            for ($i = 0;$i<strlen($value);$i++){
                $output .= chr(ord($value[$i]) + 1);
            }

            return $output;
        }
    }
    
    class InboundMessageResult
    {
        public $Code;
        public $Id;
        public $Source;
        public $Destination;
        public $Received;
        public $SourceId;
        public $DeliveryReceipt;
        public $Text;
        public $ErrorMessage;

        public function HasError() {
            
        }

        private function __construct($code, $id, $source, $destination, $received, $sourceId, $deliveryReceipt, $text, $errorMessage = '')
        {
            $this->Code = $code;
            $this->Id = $id;
            $this->Source = $source;
            $this->Destination = $destination;
            $this->Received = $received;
            $this->SourceId = $sourceId;
            $this->DeliveryReceipt = $deliveryReceipt;
            $this->Text = $text;
            $this->ErrorMessage = $errorMessage;
        }

        public static function fromString($input)
        {
            $results = array();
            if (empty($input))
            {
                return $results;
            }

            $index = stripos($input, '-');
            $code = substr($input, 0, $index);
            if (strtoupper($code) =="OK")
            {
                $input = substr($input, 3);
                if (strtoupper($input) != "NOMESSAGES")
                {
                    while (strlen($input) > 0)
                    {
                        if (stripos($input,",,") == 0)
                        {
                            $input = substr($input, 2);
                        }

                        $message = InboundMessageResult::parseForMessage($input);
                        $successParts = explode(',',$message);

                        array_push($results, new InboundMessageResult($code, $successParts[0], $successParts[1], $successParts[2], $successParts[3],
                                                             $successParts[4], $successParts[5], $successParts[8]));

                        $input = substr($input, strlen($message));
                    }
                }
            }
            else
            {
                array_push($results, new InboundMessageResult($code, 0,'','','',0,false,'',substr($input, ($index+1))));
            }

            return $results;
        }

        private static function parseForMessage($message)
        {
            $result = '';
            $parseCount = 0;
            while ($parseCount<8)
            {
                $endOfCurrentItem = stripos($message, ",");

                if ($endOfCurrentItem == -1)
                {
                    $result .= $message;
                    break;
                }

                $messagePart = substr($message, 0, $endOfCurrentItem);

                $result .= $messagePart . ',';
                $message = substr($message, $endOfCurrentItem + 1);

                if ($parseCount == 7)
                {
                    $byteCount = $messagePart;
                    $result .= mb_substr($message,0,$byteCount,'ASCII');
                }

                $parseCount++;
            }

            return $result;
        }

    }    
}
?>