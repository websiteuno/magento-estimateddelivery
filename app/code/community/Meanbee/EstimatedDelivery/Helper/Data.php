<?php

class Meanbee_EstimatedDelivery_Helper_Data extends Mage_Core_Helper_Abstract {
    const XML_PATH_ENABLED = 'meanbee_estimateddelivery/general/enabled';
    const DISPATCH = 1;
    const DELIVERY = 2;

    protected $_estimatedDeliveryData = array();
    protected $_dispatchDate = array();
    protected $_estimatedDeliveryFrom = array();
    protected $_estimatedDeliveryTo = array();

    public function canShowEstimatedDelivery($shippingMethod) {
        $enabled = $this->getEnabled();
        $hasData = $this->_getEstimatedDeliveryData($shippingMethod)->getId();
        return $enabled && $hasData;
    }

    public function getEstimatedDeliveryText($shippingMethod, $date = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $from = $this->getEstimatedDeliveryFromString($shippingMethod, $date);
        $to = $this->getEstimatedDeliveryToString($shippingMethod, $date);
        if ($from == $to) {
            $result = sprintf(($this->getSelectSlotResolution($shippingMethod) ? 'Earliest' : 'Estimated') . ' delivery: %s.', $from);
        } else {
            $result = sprintf(($this->getSelectSlotResolution($shippingMethod) ? 'Earliest' : 'Estimated') . ' delivery: %s - %s.', $from, $to);
        }

        return $result;
    }

    /**
     * Compute the estimated delivery upper bound. $date default to today's date if not provided.
     *
     * @param $shippingMethod
     * @param Zend_Date|null $date
     * @return Zend_Date
     */
    public function getEstimatedDeliveryTo($shippingMethod, $date = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $date = $this->_initDate($date);
        $cacheKey = $shippingMethod.$date->toString('ddMMyyHHmm');

        if (isset($this->_estimatedDeliveryTo[$cacheKey]) && $cached = $this->_estimatedDeliveryTo[$cacheKey]) {
            return $cached;
        }

        $deliveryFromDate = $this->getEstimatedDeliveryFrom($shippingMethod, $date);
        $estimatedDelivery = $this->_getEstimatedDeliveryData($shippingMethod);
        $offset = $estimatedDelivery->getEstimatedDeliveryTo() - $estimatedDelivery->getEstimatedDeliveryFrom();
        $deliveryToDate = $this->_computeEstimatedDelivery($shippingMethod, $deliveryFromDate, $offset);

        $this->_estimatedDeliveryTo[$cacheKey] = $deliveryToDate;

        return $deliveryToDate;
    }


    /**
     * Compute the estimated delivery lower bound. $date default to today's date if not provided.
     *
     * @param $shippingMethod
     * @param Zend_Date|null $date
     * @return Zend_Date
     */
    public function getEstimatedDeliveryFrom($shippingMethod, $date = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $date = $this->_initDate($date);
        $cacheKey = $shippingMethod.$date->toString('ddMMyyHHmm');

        if (isset($this->_estimatedDeliveryFrom[$cacheKey]) && $cached = $this->_estimatedDeliveryFrom[$cacheKey]) {
            return $cached;
        }

        $dispatchDate = $this->getDispatchDate($shippingMethod, $date);
        $estimatedDelivery = $this->_getEstimatedDeliveryData($shippingMethod);
        $deliveryFromDate = $this->_computeEstimatedDelivery($shippingMethod, $dispatchDate, $estimatedDelivery->getEstimatedDeliveryFrom());

        $this->_estimatedDeliveryFrom[$cacheKey] = $deliveryFromDate;

        return $deliveryFromDate;
    }

    /**
     * Compute the date of dispatch. $date defaults to today if not provided.
     *
     * @param $shippingMethod
     * @param Zend_Date|null $date
     * @return Zend_Date
     */
    public function getDispatchDate($shippingMethod, $date = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $date = $this->_initDate($date);
        $cacheKey = $shippingMethod.$date->toString('ddMMyyHHmm');

        if (isset($this->_dispatchDate[$cacheKey]) && $cached = $this->_dispatchDate[$cacheKey]) {
            return $cached;
        }

        $estimatedDelivery = $this->_getEstimatedDeliveryData($shippingMethod);

        $date = $this->_handleLatestDispatch($estimatedDelivery, $date);
        $date = $this->_handleDispatchPreparation($estimatedDelivery, $date);
        $date = $this->_computeClosestValidDate($estimatedDelivery, $date, self::DISPATCH);

        $this->_dispatchDate[$cacheKey] = $date;

        return $date;
    }

    /**
     * Helper method around getEstimatedDeliveryFrom which returns the estimated delivery as a formatted date string,
     * rather than a Zend_Date.
     *
     * @param $shippingMethod
     * @param Zend_Date|null $date
     * @param string $format
     * @return string
     */
    public function getEstimatedDeliveryFromString($shippingMethod, $date = null, $format = 'EEEE, dSS MMMM') {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $result = $this->getEstimatedDeliveryFrom($shippingMethod, $date);
        return $result->toString($format);
    }

    /**
     * Helper method around getEstimatedDeliveryTo which returns the estimated delivery as a formatted date string,
     * rather than a Zend_Date.
     *
     * @param $shippingMethod
     * @param Zend_Date|null $date
     * @param string $format
     * @return string
     */
    public function getEstimatedDeliveryToString($shippingMethod, $date = null, $format = 'EEEE, dSS MMMM') {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $result = $this->getEstimatedDeliveryTo($shippingMethod, $date);
        return $result->toString($format);
    }

    /**
     * Helper method around getDispatchDate which returns the estimated delivery as a formatted date string,
     * rather than a Zend_Date.
     *
     * @param $shippingMethod
     * @param Zend_Date|null $date
     * @param string $format
     * @return string
     */
    public function getDispatchDateString($shippingMethod, $date = null, $format = 'EEEE, dSS MMMM') {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $result = $this->getDispatchDate($shippingMethod, $date);
        return $result->toString($format);
    }

    /**
     * Method used to retrieve the number of days from $startdate until the estimated delivery from date.
     *
     * @param $shippingMethod
     * @param Zend_Date $startDate
     * @return int
     */
    public function getDaysUntilEstimatedDeliveryFrom($shippingMethod, $startDate = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $endDate = $this->getEstimatedDeliveryFrom($shippingMethod, $startDate);
        return $this->_getDifferenceInDays($endDate, $startDate);

    }

    /**
     * Method used to retrieve the number of days from $startdate until the estimated delivery to date.
     *
     * @param $shippingMethod
     * @param Zend_Date $startDate
     * @return int
     */
    public function getDaysUntilEstimatedDeliveryTo($shippingMethod, $startDate = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $endDate = $this->getEstimatedDeliveryTo($shippingMethod, $startDate);
        return $this->_getDifferenceInDays($endDate, $startDate);
    }

    /**
     * Method used to retrieve the number of days from $startdate until the dispatch date.
     *
     * @param $shippingMethod
     * @param Zend_Date $startDate
     * @return int
     */
    public function getDaysUntilDispatchDate($shippingMethod, $startDate = null) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        $endDate = $this->getDispatchDate($shippingMethod, $startDate);
        return $this->_getDifferenceInDays($endDate, $startDate);
    }


    public function getSelectSlotResolution($shippingMethod) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        return $this->_getEstimatedDeliveryData($shippingMethod)->getSelectSlotResolution();
    }

    public function getSelectSlotUpperLimit($shippingMethod) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        return $this->_getEstimatedDeliveryData($shippingMethod)->getSelectSlotUpperLimit();
    }

    public function getDeliverableDays($shippingMethod) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        return $this->_getEstimatedDeliveryData($shippingMethod)->getDeliverableDays();
    }

    public function getDeliveryDayHolidays($shippingMethod) {
        if (!$this->canShowEstimatedDelivery($shippingMethod)) {
            return false;
        }

        return $this->_getEstimatedDeliveryData($shippingMethod)->getData('delivery_day_holidays');
    }


    public function getEnabled() {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }


    /**
     * Helper method used to initialise $date to today if set to null
     *
     * @param $date
     * @return Zend_Date
     */
    protected function _initDate($date) {
        if ($date === null) {
            $date =  Mage::app()->getLocale()->storeDate(Mage::app()->getStore(), null, true);
        }
        return $date;
    }

    /**
     * Helper method to compute the difference in days between two dates. If $startDate is not provided then default to today
     *
     * @param $endDate
     * @param $startDate
     * @return int
     */
    protected function _getDifferenceInDays($endDate, $startDate = null) {
        $startDate = $this->_initDate($startDate);

        $startDate = clone $startDate;
        $endDate = clone $endDate;

        $difference = $endDate->sub($startDate);

        $measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
        $measure->convertTo(Zend_Measure_Time::DAY);

        // The time to complete the request can take some time, so round it up. This prevents problems like:
        // You should then receive your order in 4 - 10.9999884259259259259259259 days.
        $roundedValue = round($measure->getValue());

        return $roundedValue;
    }

    /**
     * Helper method, sets up the call to _computeClosestValidDate. $offset is the amount to increment the date by - see
     * how this is used in getEstimateDeliveryFrom and getEstimateDeliveryTo.
     *
     * @param array $shippingMethod
     * @param Zend_Date $date
     * @param int $offset
     * @return Zend_Date
     */
    protected function _computeEstimatedDelivery($shippingMethod, $date, $offset) {
        $localDate = clone $date;
        $estimatedDelivery = $this->_getEstimatedDeliveryData($shippingMethod);

        $region = $estimatedDelivery->getDeliveryTimeHolidays();
        $holidayHelper = Mage::helper('meanbee_estimateddelivery/bankHoliday');
        for ($i = $offset; $i > 0; $localDate->addDay(1)) {
            if ($region === null || !$holidayHelper->isHoliday($localDate, $region)) {
                $i--;
            }
        }

        $localDate = $this->_computeClosestValidDate($estimatedDelivery, $localDate, self::DELIVERY);

        return $localDate;
    }

    /**
     * Helper method which finds the closest valid day for a given date. Shipping methods have certain days which they
     * can be either delivered or dispatched and this helper method facilitates finding the closest day.
     *
     * For example, imagine "freeshipping" can be dispatched on Monday to Friday, and an order is placed on Sunday.
     * This method would return the date of the Monday following the Sunday which the order was placed.
     *
     * @param EstimatedDelivery_Model_Estimateddelivery $estimatedDelivery
     * @param Zend_Date $date
     * @param int $phase
     * @return Zend_Date
     */
    protected function _computeClosestValidDate($estimatedDelivery, $date, $phase) {
        $localDate = clone $date;
        $region = null;
        switch ($phase) {
            case self::DISPATCH:
                $validDays = $estimatedDelivery->getDispatchableDays();
                $region = $estimatedDelivery->getDispatchDayHolidays();
                break;
            case self::DELIVERY:
                $validDays = $estimatedDelivery->getDeliverableDays();
                $region = $estimatedDelivery->getDeliveryDayHolidays();
                break;
        }
        $holidayHelper = Mage::helper('meanbee_estimateddelivery/bankHoliday');
        while(true) {
            $day = $localDate->toString(Zend_Date::WEEKDAY_DIGIT);
            if (in_array($day, $validDays) && ($region === null || !$holidayHelper->isHoliday($localDate, $region))) {
                break;
            }
            $localDate->addDay(1);
        }
        return $localDate;
    }

    protected function _getEstimatedDeliveryData($shippingMethod) {
        if (isset($this->_estimatedDeliveryData[$shippingMethod]) && $data = $this->_estimatedDeliveryData[$shippingMethod]) {
            return $data;
        }
        $data = Mage::getModel('meanbee_estimateddelivery/estimateddelivery')->loadByShippingMethod($shippingMethod);
        $this->_estimatedDeliveryData[$shippingMethod] = $data;
        return $data;
    }

    /**
     * If we are past the latest dispatch point on a day, increment the day by one, since this means the order would
     * be dispatched on the following day.
     *
     * @param Meanbee_EstimatedDelivery_Model_Estimateddelivery $estimatedDelivery
     * @param Zend_Date $date
     * @return Zend_Date
     */
    protected function _handleLatestDispatch($estimatedDelivery, $date) {
        $localDate = clone $date;
        $latestDispatchTime = intval(str_replace(':', '', $estimatedDelivery->getLastDispatchTime()));
        $currentTime = intval($localDate->toString('HHmmss'));

        if ($currentTime >= $latestDispatchTime) {
            $localDate->addDay(1);
        }

        return $localDate;
    }

    /**
     * Increment the date by the dispatch prepartion amount configured in admin.
     *
     * @param Meanbee_EstimatedDelivery_Model_Estimateddelivery $estimatedDelivery
     * @param Zend_Date $date
     * @return Zend_Date
     */
    protected function _handleDispatchPreparation($estimatedDelivery, $date) {
        $localDate = clone $date;
        $dispatchPreparation = $estimatedDelivery->getDispatchPreparation();

        if ($region = $estimatedDelivery->getDispatchTimeHolidays()) {
            $holidayHelper = Mage::helper('meanbee_estimateddelivery/bankHoliday');
            $dispatchPreparation = $estimatedDelivery->getDispatchPreparation();
            for ($i = $dispatchPreparation; $i > 0; $localDate->addDay(1)) {
                if (!$holidayHelper->isHoliday($localDate, $region)) {
                    $i--;
                }
            }
        } else {
            $localDate->addDay($dispatchPreparation);
        }

        return $localDate;
    }

    public function getDelayedDispatch($shippingMethod, $deliveryDate) {
        $dispatchDate = clone $deliveryDate;
        $estimatedDelivery = $this->_getEstimatedDeliveryData($shippingMethod);
        $deliveryDurationMax = $estimatedDelivery->getEstimatedDeliveryTo();
        $dispatchableDays = $estimatedDelivery->getDispatchableDays();
        
        $holidayHelper = Mage::helper('meanbee_estimateddelivery/bankHoliday');
        if ($region = $estimatedDelivery->getDeliveryTimeHolidays($shippingMethod)) {
            for ($i = $deliveryDurationMax; $i > 0; $dispatchDate->subDay(1)) {
                if (!$holidayHelper->isHoliday($dispatchDate, $region)) {
                    $i--;
                }
            }
        } else {
            $dispatchDate->subDay($deliveryDurationMax);
        }
        if ($region = $estimatedDelivery->getDispatchDayHolidays()) {
            while (array_search($dispatchDate->toString(Zend_Date::WEEKDAY_DIGIT), $dispatchableDays) === false || $holidayHelper->isHoliday($dispatchDate, $region)) {
                $dispatchDate->subDay(1);
            }
        } else {
            while (array_search($dispatchDate->toString(Zend_Date::WEEKDAY_DIGIT), $dispatchableDays) === false) {
                $dispatchDate->subDay(1);
            }
        }

        return $dispatchDate;
    }
}
