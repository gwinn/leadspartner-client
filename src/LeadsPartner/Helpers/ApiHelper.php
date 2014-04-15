<?php

namespace LeadsPartner\Helpers;

use IntaroCrm\Exception\ApiException;
use IntaroCrm\Exception\CurlException;
use IntaroCrm\RestApi;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiHelper {
    private $dir, $fileDate, $errDir;
    protected $intaroApi, $log, $params;

    protected function initLogger() {
        $this->log = new Logger('leadspartner');
        $this->log->pushHandler(new StreamHandler($this->dir . 'log/leadspartner.log', Logger::INFO));
    }

    public function __construct() {
        $this->dir = __DIR__ . '/../../../';
        $this->fileDate = $this->dir . 'log/historyDate.log';
        $this->errDir = $this->dir . 'log/json';
        $this->params = parse_ini_file($this->dir . 'config/parameters.ini', true);

        $this->intaroApi = new RestApi(
            $this->params['intarocrm_api']['url'],
            $this->params['intarocrm_api']['key']
        );

        $this->initLogger();
    }

    public function orderCreate($order) {
        $order['customFields'] = $order['customFields'] + $this->getAdditionalParameters();;

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
                'Error: IntaroCRM - LeadsPartner',
                '<p> RestApi::customers:' . $e->getMessage() . '</p>' .
                '<p> RestApi::customers:' . json_encode($order) . '</p>'
            );
        } catch (CurlException $e) {
            $this->log->addError('RestApi::customers::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - LeadsPartner',
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
                'Error: IntaroCRM - LeadsPartner',
                '<p> RestApi::orderCreate:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderCreate:' . json_encode($order) . '</p>'
            );
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderCreate::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - LeadsPartner',
                '<p> RestApi::orderCreate::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderCreate::Curl:' . json_encode($order) . '</p>'
            );
        }

    }

    public function orderHistory() {
        $this->sendErrorJson();

        try {
            $statuses = $this->intaroApi->orderStatusGroupsList();
        } catch (ApiException $e) {
            $this->log->addError('RestApi::orderStatusGroupsList:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - LeadsPartner',
                '<p> RestApi::RestApi::orderStatusGroupsList:' . $e->getMessage() . '</p>'
            );

            return false;
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderStatusGroupsList::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - LeadsPartner',
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
                'Error: IntaroCRM - LeadsPartner',
                '<p> RestApi::orderHistory:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderHistory:' . json_encode($orders) . '</p>'
            );

            return false;
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderHistory::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - LeadsPartner',
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
                        'Error: IntaroCRM - LeadsPartner',
                        '<p> RestApi::orderGet:' . $e->getMessage() . '</p>' .
                        '<p> RestApi::orderGet:' . json_encode($order) . '</p>'
                    );

                    return false;
                } catch (CurlException $e) {
                    $this->log->addError('RestApi::orderGet::Curl:' . $e->getMessage());

                    $this->sendMail(
                        'Error: IntaroCRM - LeadsPartner',
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
                        'Error: IntaroCRM - LeadsPartner',
                        '<p> RestApi::orderGet:' . $e->getMessage() . '</p>' .
                        '<p> RestApi::orderGet:' . json_encode($order) . '</p>'
                    );

                    return false;
                } catch (CurlException $e) {
                    $this->log->addError('RestApi::orderGet::Curl:' . $e->getMessage());

                    $this->sendMail(
                        'Error: IntaroCRM - LeadsPartner',
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

    public function sendLP($status, $transaction, $timesSent = 1) {
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

        if(!isset($result['result']) && !$result['resut'] && $result['result'] !== 'ok') {
            $data['times_sent'] = $timesSent;

            if($data['times_sent'] < 4)
                $this->writeErrorJson($data);

            $this->log->addError('RestApi::leadspartner::Curl:' . json_encode($result));
            $this->log->addError('RestApi::leadspartner::Curl:' . json_encode($data));
        }

        return $result;
    }

    public function setAdditionalParameters($query)
    {
        if(!$query) return;

        $params = [];
        parse_str($query, $params);
        $params = array_merge($this->getAdditionalParameters(), $params);

        foreach ($params as $key => $param) {
            if (empty($param)) {
                unset($params[$key]);
            }
        }

        setcookie($this->params['cookie_name'], serialize($params), time() + 60 * 60 * 24 * 365, '/');
    }

    public function getAdditionalParameters()
    {
        if (!isset($_COOKIE[$this->params['cookie_name']])) {
            return [];
        }

        return unserialize($_COOKIE[$this->params['cookie_name']]);
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

    private function sendErrorJson() {
        foreach($this->getErrorFiles() as $fileName) {
            $result = $this->getErrorJson($fileName);
            $this->unlinkErrorJson($fileName);
            if(isset($result['status']) && $result['status'] && isset($result['transaction_id'])
                && $result['transaction_id'] && isset($result['times_sent'])
                && $result['times_sent'] && $result['times_sent'] < 4)
                $this->sendLP($result['status'], $result['transaction_id'], $result['times_sent'] + 1);
        }
    }

    private function getErrorFiles() {
        return glob($this->errDir . '/err_*.json', GLOB_BRACE);
    }

    private function unlinkErrorJson($fileName) {
        unlink($this->errDir . '/' . $fileName);
    }

    private function getErrorJson($fileName) {
        $result = file_get_contents($this->errDir . '/' . $fileName);
        if(!$result) return [];
        $result = json_encode($result, true);
        if(is_array($result)) return $result;
        else return [];
    }

    private function writeErrorJson(array $data) {
        file_put_contents($this->getErrFileName(), json_encode($data), LOCK_EX);
    }

    private function getErrFileName() {
        return $this->errDir . '/err_' . (microtime(true) * 10000) . mt_rand(1, 1000) .'.json';
    }
}
