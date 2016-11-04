<?php
    namespace Lacodda\BizTrip;

    defined ('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

    /**
     * Exchange currency rates class
     *
     * The PHP class to gets exchange currency rates from webservice of Central Bank of Russia
     *
     * @author  Lacodda
     * @link    http://lacodda.com
     * @version 1.0
     */
    class ExchangeRates
    {

        /**
         * @var array
         */
        private static $currency = array ('USD', 'EUR', 'KRW');

        /**
         * @var bool
         */
        private static $only_main_currency = true;

        /**
         * @var
         */
        private static $date;

        /**
         * @var string
         */
        private static $currencyLogFilePath;

        /**
         * @var string
         */
        private static $currencyLogFile = 'currency_log.json';

        /**
         * The exchange rates on defined date
         *
         * @var array
         */
        public static $rates = array ();

        /**
         * This method creates a connection to webservice of Central Bank of Russia
         * and obtains exchange rates, parse it and fills $rates property
         *
         * @param string $date The date on which exchange rates will be obtained
         */
        public function __construct ()
        {
            self::setPath ();
        }

        /**
         *
         */
        private static function setPath ()
        {
            self::$currencyLogFilePath = $_SERVER['DOCUMENT_ROOT'] . \COption::GetOptionInt ('lacodda.biztrip', 'CUR_FILE_LOG');

            self::$currencyLogFile = self::$currencyLogFilePath . '/' . self::$currencyLogFile;
        }

        /**
         * @param null $date
         */
        private static function setDate ($date = null)
        {
            self::$date = date ('Y-m-d');

            if ($date)
            {
                self::$date = date ('Y-m-d', strtotime ($date));
            }
        }

        /**
         * @param $path
         */
        public static function setLogFilePath ($path)
        {
            self::$currencyLogFilePath = $_SERVER['DOCUMENT_ROOT'] . $path;

            self::$currencyLogFile = self::$currencyLogFilePath . '/' . self::$currencyLogFile;
        }

        /**
         * @param $log
         */
        private static function setLog ($log)
        {
            $log = json_encode ($log);
            if (!file_exists (self::$currencyLogFilePath))
            {
                mkdir (self::$currencyLogFilePath);

                chmod (self::$currencyLogFilePath, 0777);
            }
            file_put_contents (self::$currencyLogFile, $log);
        }

        /**
         * @return bool|mixed
         */
        private static function getLog ()
        {
            if (file_exists (self::$currencyLogFile) && $log = file_get_contents (self::$currencyLogFile))
            {
                $json_log = json_decode ($log, true);

                return $json_log;
            } else
            {
                return false;
            }
        }

        /**
         * @param $log
         */
        private static function putLog ($log)
        {
            $log_file = self::getLog ();

            self::setLog (array_merge ($log_file, $log));
        }

        /**
         * @return array
         */
        public static function getExchangeRatesCBRF ()
        {
            $client = new \SoapClient("http://www.cbr.ru/DailyInfoWebServ/DailyInfo.asmx?WSDL");

            $curs = $client->GetCursOnDate (array ("On_date" => self::$date));

            $rates = new \SimpleXMLElement($curs->GetCursOnDateResult->any);

            $result = array ();

            foreach ($rates->ValuteData->ValuteCursOnDate as $rate)
            {
                $r = number_format ((float) $rate->Vcurs / (int) $rate->Vnom, 4, '.', '');
                $result[self::$date]['CharCode'][(string) $rate->VchCode] = $r;
                $result[self::$date]['NumCode'][(int) $rate->Vcode] = $r;
            }

            $result[self::$date]['CharCode']['RUB'] = 1;

            $result[self::$date]['NumCode'][643] = 1;

            return $result;
        }

        /**
         * @return mixed
         */
        public static function getExchangeRates ()
        {
            $rates = self::getLog ();

            if ($rates && $rates[self::$date])
            {
                return $rates[self::$date];
            } else
            {
                $ratesCBRF = self::getExchangeRatesCBRF ();

                if (self::$only_main_currency)
                {
                    $ratesCBRF[self::$date] = self::incCurrency ($ratesCBRF[self::$date]);
                }
                if ($rates)
                {
                    self::putLog ($ratesCBRF);
                } else
                {
                    self::setLog ($ratesCBRF);
                }

                return $ratesCBRF[self::$date];
            }
        }

        /**
         * @param $rates
         *
         * @return array
         */
        public static function incCurrency ($rates)
        {
            $result = array ();

            foreach (self::$currency as $key)
            {
                $result['CharCode'][$key] = $rates['CharCode'][$key];
            }

            return $result;
        }

        /**
         * This method returns the array of exchange rates
         *
         * @return array The exchange rates
         */
        public static function getRatesAll ($date = null)
        {
            self::setDate ($date);

            return self::getExchangeRates ();
        }

        /**
         * This method returns the array of exchange rates
         *
         * @return array The exchange rates
         */
        public static function getRates ($code_arr = array (), $date = null)
        {
            if (is_array ($code_arr) && !empty($code_arr))
            {
                $result = array ();

                foreach ($code_arr as $code)
                {
                    $result[$code] = self::getRate ($code, $date);
                }

                return $result;
            } else
            {
                return self::getRatesAll ($date);
            }
        }

        /**
         * This method returns exchange rate of given currency by its code
         *
         * @param mixed $code The alphabetic or numeric currency code
         *
         * @return float The exchange rate of given currency
         */
        public static function getRate ($code, $date = null)
        {
            self::setDate ($date);

            $rates = self::getExchangeRates ();

            if (is_string ($code))
            {
                $code = strtoupper (trim ($code));

                return (isset($rates['CharCode'][$code])) ? $rates['CharCode'][$code] : false;
            } elseif (is_numeric ($code))
            {
                return (isset($rates['NumCode'][$code])) ? $rates['NumCode'][$code] : false;
            } else
            {
                return false;
            }
        }

        /**
         * This method returns exchange rate of given currency by its code
         *
         * @param mixed $CurCodeToSell The alphabetic or numeric currency code to sell
         * @param mixed $CurCodeToBuy  The alphabetic or numeric currency code to buy
         *
         * @return float The cross exchange rate of given currencies
         */
        public static function getCrossRate ($CurCodeToSell, $CurCodeToBuy, $date = null)
        {
            self::setDate ($date);

            $CurToSellRate = self::getRate ($CurCodeToSell, $date);

            $CurToBuyRate = self::getRate ($CurCodeToBuy, $date);

            if ($CurToSellRate && $CurToBuyRate)
            {
                return $CurToBuyRate / $CurToSellRate;
            } else
            {
                return false;
            }
        }

    }