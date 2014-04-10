<?php

namespace LeadsPartner\Helpers;

use IntaroCrm\Exception\ApiException;
use IntaroCrm\Exception\CurlException;
use IntaroCrm\RestApi;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiHelper {
    private $dir, $fileDate;
    protected $intaroApi, $log, $params;

    protected function initLogger() {
        $this->log = new Logger('varifort');
        $this->log->pushHandler(new StreamHandler($this->dir . 'log/varifort.log', Logger::INFO));
    }

    public function __construct() {
        $this->dir = __DIR__ . '/../../../';
        $this->fileDate = $this->dir . 'log/historyDate.log';
        $this->params = parse_ini_file($this->dir . 'config/parameters.ini', true);

        $this->intaroApi = new RestApi(
            $this->params['intarocrm_api']['url'],
            $this->params['intarocrm_api']['key']
        );

        $this->initLogger();
    }

    public function orderCreate($order) {
        $queryArr = [];

        if(isset($order['query']) && $order['query'])
            parse_str($order['query'], $queryArr);

        $order['customFields'] = $order['customFields'] + $queryArr;

        if(isset($order['customer']['fio'])) {
            $contactNameArr = $this->explodeFIO($order['customer']['fio']);

            // parse fio
            if(count($contactNameArr) == 1) {
                $order['firstName']              = $contactNameArr[0];
                $order['customer']['firstName']  = $contactNameArr[0];
            } else {
                $order['lastName']               = $contactNameArr[0];
                $order['customer']['lastName']   = $contactNameArr[0];
                $order['firstName']              = $contactNameArr[1];
                $order['customer']['firstName']  = $contactNameArr[1];
                $order['patronymic']             = $contactNameArr[2];
                $order['customer']['patronymic'] = $contactNameArr[2];
            }
        }

        if(isset($order['customer']['phone'][0]) && $order['customer']['phone'][0])
            $order['phone'] = $order['customer']['phone'][0];

        try {
            $customers = $this->intaroApi->customers(
                isset($order['phone']) ? $order['phone'] : null,
                null, $order['customer']['fio'], 200, 0);

        } catch (ApiException $e) {
            $this->log->addError('RestApi::customers:' . $e->getMessage());
            $this->log->addError('RestApi::customers:' . json_encode($order));

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::customers:' . $e->getMessage() . '</p>' .
                '<p> RestApi::customers:' . json_encode($order) . '</p>'
            );
        } catch (CurlException $e) {
            $this->log->addError('RestApi::customers::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::customers::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::customers::Curl:' . json_encode($order) . '</p>'
            );
        }

        if(count($customers) > 0)
            $order['customerId'] = current($customers)['externalId'];
        else
            $order['customerId'] = (microtime(true) * 10000) . mt_rand(1, 1000);

        $order['customer']['externalId'] = $order['customerId'];

        try {
            $this->intaroApi->customerEdit($order['customer']);
            unset($order['customer']);

            $this->intaroApi->orderCreate($order);
        } catch (ApiException $e) {
            $this->log->addError('RestApi::orderCreate:' . $e->getMessage());
            $this->log->addError('RestApi::orderCreate:' . json_encode($order));

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::orderCreate:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderCreate:' . json_encode($order) . '</p>'
            );
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderCreate::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::orderCreate::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderCreate::Curl:' . json_encode($order) . '</p>'
            );
        }

    }

    public function orderHistory() {

        try {
            $statuses = $this->intaroApi->orderStatusGroupsList();
        } catch (ApiException $e) {
            $this->log->addError('RestApi::orderStatusGroupsList:' . $e->getMessage());

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::RestApi::orderStatusGroupsList:' . $e->getMessage() . '</p>'
            );

            return false;
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderStatusGroupsList::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::orderStatusGroupsList::Curl:' . $e->getMessage() . '</p>'
            );

            return false;
        }

        try {
            $orders = $this->intaroApi->orderHistory($this->getDate());
            $this->saveDate($this->intaroApi->getGeneratedAt()->format('Y-m-d H:i:s'));
        } catch (ApiException $e) {
            $this->log->addError('RestApi::orderHistory:' . $e->getMessage());
            $this->log->addError('RestApi::orderHistory:' . json_encode($orders));

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::orderHistory:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderHistory:' . json_encode($orders) . '</p>'
            );

            return false;
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderHistory::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: varifort.ru',
                '<p> RestApi::orderHistory::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderHistory::Curl:' . json_encode($orders) . '</p>'
            );

            return false;
        }


        foreach($orders as $order) {

            if(isset($order['status']) && $order['status']
                && $this->isInGroup($order['status'], $statuses['approval']['statuses'])) {
                try {
                    $o = $this->intaroApi->orderGet($order['id'], 'id');
                } catch (ApiException $e) {
                    $this->log->addError('RestApi::orderGet:' . $e->getMessage());
                    $this->log->addError('RestApi::orderGet:' . json_encode($order));

                    $this->sendMail(
                        'Error: varifort.ru',
                        '<p> RestApi::orderGet:' . $e->getMessage() . '</p>' .
                        '<p> RestApi::orderGet:' . json_encode($order) . '</p>'
                    );

                    return false;
                } catch (CurlException $e) {
                    $this->log->addError('RestApi::orderGet::Curl:' . $e->getMessage());

                    $this->sendMail(
                        'Error: varifort.ru',
                        '<p> RestApi::orderGet::Curl:' . $e->getMessage() . '</p>' .
                        '<p> RestApi::orderGet::Curl:' . json_encode($order) . '</p>'
                    );

                    return false;
                }

                if(isset($o['customFields']) && isset($o['customFields']['transaction_id'])
                    && $o['customFields']['transaction_id'])
                    $this->sendLP("approved", $o['customFields']['transaction_id']);

            } elseif(isset($order['status']) && $order['status']
                && $this->isInGroup($order['status'], $statuses['cancel']['statuses'])) {
                try {
                    $o = $this->intaroApi->orderGet($order['id'], 'id');
                } catch (ApiException $e) {
                    $this->log->addError('RestApi::orderGet:' . $e->getMessage());
                    $this->log->addError('RestApi::orderGet:' . json_encode($order));

                    $this->sendMail(
                        'Error: varifort.ru',
                        '<p> RestApi::orderGet:' . $e->getMessage() . '</p>' .
                        '<p> RestApi::orderGet:' . json_encode($order) . '</p>'
                    );

                    return false;
                } catch (CurlException $e) {
                    $this->log->addError('RestApi::orderGet::Curl:' . $e->getMessage());

                    $this->sendMail(
                        'Error: varifort.ru',
                        '<p> RestApi::orderGet::Curl:' . $e->getMessage() . '</p>' .
                        '<p> RestApi::orderGet::Curl:' . json_encode($order) . '</p>'
                    );

                    return false;
                }

                if(isset($o['customFields']) && isset($o['customFields']['transaction_id'])
                    && $o['customFields']['transaction_id'])
                    $this->sendLP("rejected", $o['customFields']['transaction_id']);
            }
        }

        return true;
    }

    private function saveDate($date) {
        file_put_contents($this->fileDate, $date, LOCK_EX);
    }

    private function getDate() {
        $result = file_get_contents($this->fileDate);
        if(!$result) {
            $result = new \DateTime();
            return $result->format('Y-m-d H:i:s');
        } else return $result;
    }

    public function sendLP($status, $transaction) {
        if(!$status || !$transaction)
            return false;

        $data = [
            "login"          => $this->params['leadspartner']['login'],
            "password"       => $this->params['leadspartner']['password'],
            "Target"         => $this->params['leadspartner']['target'],
            "transaction_id" => $transaction,
            "status"         => $status
        ];

        $data_string = json_encode($data);
        $ch = curl_init($this->params['leadspartner']['url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
               ['Content-Type: application/json',
                'Content-Length: ' . strlen($data_string)]
        );

        $result = curl_exec($ch);
        $this->log->addError('RestApi::leadspartner::Curl:' . json_encode($result));
        //$this->log->addError('RestApi::leadspartner::Curl:' . json_encode($data));

        return $result;
    }

    protected function sendMail($subject, $body) {
        if(!$subject || !$body || !$this->params['mail']['from'] || !$this->params['mail']['to'])
            return false;

        mail($this->params['mail']['to'], $subject, $body, 'From:'.$this->params['mail']['from']);
    }

    private function explodeFIO($str) {
        if(!$str)
            return [];

        $array = explode(" ", $str, 3);
        $newArray = [];

        foreach($array as $ar) {
            if(!$ar)
                continue;

            $newArray[] = $ar;
        }

        return $newArray;
    }

    private function isInGroup($status, $statuses) {
        foreach($statuses as $s)
            if($s == $status) return true;

        return false;
    }
}
