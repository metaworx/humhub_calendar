<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */

namespace humhub\modules\calendar\models\forms;

use humhub\modules\calendar\interfaces\recurrence\RecurrenceFormModel;
use humhub\modules\calendar\models\forms\validators\CalendarDateFormatValidator;
use humhub\modules\calendar\models\forms\validators\CalendarEndDateValidator;
use humhub\modules\calendar\models\forms\validators\CalendarTypeValidator;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\topic\models\Topic;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use DateTimeZone;
use humhub\modules\calendar\helpers\CalendarUtils;
use humhub\modules\calendar\models\CalendarEntryType;
use humhub\modules\calendar\models\CalendarEntry;

/**
 * Created by PhpStorm.
 * User: buddha
 * Date: 12.07.2017
 * Time: 16:14
 */
class CalendarEntryForm extends Model
{
    /**
     * @var integer Content visibility
     */
    public $is_public;

    /**
     * @var string start date submitted by user will be converted to db date format and timezone after validation
     */
    public $start_date;

    /**
     * @var string start time string
     */
    public $start_time;

    /**
     * @var string end date submitted by user will be converted to db date format and timezone after validation
     */
    public $end_date;

    /**
     * @var string end time string
     */
    public $end_time;

    /**
     * @var string timeZone set in calendar form
     */
    public $timeZone;

    /**
     * @var int calendar event type id
     */
    public $type_id;

    /**
     * @var
     */
    public $topics = [];

    /**
     * @var bool
     */
    public $sendUpdateNotification = 0;

    /**
     * @var integer if set to true all space participants will be added to the event
     */
    public $forceJoin = 0;

    /**
     * @var CalendarEntry
     */
    public $entry;

    /**
     * @var CalendarEntry
     */
    public $original;

    /**
     * @var ReminderSettings
     */
    public $reminderSettings;

    /**
     * @var RecurrenceFormModel
     */
    public $recurrenceForm;

    /**
     * @param $contentContainer
     * @param string|null $start FullCalendar start datetime e.g.: 2020-01-01 00:00:00
     * @param string|null $end FullCalendar end datetime e.g.: 2020-01-02 00:00:00
     * @return CalendarEntryForm
     * @throws Exception
     */
    public static function createEntry($contentContainer, $start = null, $end = null)
    {
        $instance = new static(['entry' => new CalendarEntry($contentContainer)]);
        $instance->updateDateRangeFromCalendar($start, $end);
        return $instance;
    }

    /**
     * @param string|null $start FullCalendar start datetime e.g.: 2020-01-01 00:00:00
     * @param string|null $end FullCalendar end datetime e.g.: 2020-01-02 00:00:00
     * @param bool $save
     * @return bool|void
     * @throws \Throwable
     */
    public function updateDateRangeFromCalendar($start = null, $end = null, $save = false)
    {
        if(!$start || !$end) {
            return;
        }

        $this->setFormDates($start, $end);

        if($save) {
            return $this->save();
        }

        return true;
    }

    public function setDefaultTime()
    {
        if($this->entry->isAllDay()) {
            $this->start_time = '10:00';
            $this->end_time = '12:00';
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['timeZone', 'in', 'range' => DateTimeZone::listIdentifiers()],
            ['topics', 'safe'],
            [['is_public', 'type_id', 'sendUpdateNotification', 'forceJoin'], 'integer'],
            [['start_time', 'end_time'], 'date', 'type' => 'time', 'format' => $this->getTimeFormat()],
            ['start_date', CalendarDateFormatValidator::class, 'timeField' => 'start_time'],
            ['end_date', CalendarDateFormatValidator::class, 'timeField' => 'end_time'],
            ['end_date', CalendarEndDateValidator::class],
            ['type_id', CalendarTypeValidator::class],
        ];
    }

    public function attributeLabels()
    {
        return [
            'start_date' => Yii::t('CalendarModule.base', 'Start Date'),
            'type_id' => Yii::t('CalendarModule.base', 'Event Type'),
            'end_date' => Yii::t('CalendarModule.base', 'End Date'),
            'start_time' => Yii::t('CalendarModule.base', 'Start Time'),
            'end_time' => Yii::t('CalendarModule.base', 'End Time'),
            'timeZone' => Yii::t('CalendarModule.base', 'Time Zone'),
            'is_public' => Yii::t('CalendarModule.base', 'Public'),
            'sendUpdateNotification' => Yii::t('CalendarModule.base', 'Send update notification'),
            'forceJoin' => ($this->entry->isNewRecord)
                ? Yii::t('CalendarModule.base', 'Add all space members to this event')
                : Yii::t('CalendarModule.base', 'Add remaining space members to this event'),
        ];
    }


    public function init()
    {
        parent::init();

        $this->timeZone = $this->entry->time_zone;
        $this->is_public = $this->entry->content->visibility;

        if(!$this->entry->isNewRecord) {
            $type = $this->entry->getType();
            if ($type) {
                $this->type_id = $type->id;
            }

            $this->topics = $this->entry->content->getTags(Topic::class);

            $this->setFormDatesFromModel();
            $this->original = CalendarEntry::findOne(['id' => $this->entry->id]);
        } else {
            $this->entry->setDefaults();
        }

        $this->reminderSettings = new ReminderSettings(['entry' => $this->entry]);
        $this->recurrenceForm = new RecurrenceFormModel(['entry' => $this->entry]);
    }

    /**
     * @throws \Exception
     */
    public function setFormDatesFromModel()
    {
        $this->setFormDates($this->entry->getStartDateTime(), $this->entry->getEndDateTime(), !$this->entry->isAllDay());
    }

    /**
     * @param $start
     * @param $end
     * @param bool $translateTimeZone
     * @throws \Exception
     */
    protected function setFormDates($start, $end, $translateTimeZone = false)
    {
        if (!$start || !$end) {
            return;
        }

        $startDt = CalendarUtils::getDateTime($start);
        $endDt = CalendarUtils::getDateTime($end);

        // Translate from moment after to moment before
        if(CalendarUtils::isAllDay($startDt, $endDt)) {
            CalendarUtils::ensureAllDay($startDt, $endDt);
        }

        if($translateTimeZone) {
            $startDt = CalendarUtils::translateTimezone($startDt, CalendarUtils::getSystemTimeZone(), $this->timeZone);
            $endDt = CalendarUtils::translateTimezone($endDt, CalendarUtils::getSystemTimeZone(), $this->timeZone);
        }

        $this->start_date = CalendarUtils::getDateString($startDt);
        $this->start_time = CalendarUtils::getTimeString($startDt, $this->getTimeFormat());
        $this->end_date = CalendarUtils::getDateString($endDt);
        $this->end_time = CalendarUtils::getTimeString($endDt, $this->getTimeFormat());

        $this->syncModelDatesFromForm();
    }

    private function syncModelDatesFromForm()
    {
        $startDt = $this->getStartDateTime();
        $endDt =  $this->getEndDateTime();

        // For all day events the time value is not submitted so we handle startDate = endDate as all day event
        $this->entry->all_day = (int) ($startDt == $endDt || CalendarUtils::isAllDay($startDt, $endDt));

        if($this->entry->isAllDay()) {
            $this->entry->start_datetime = CalendarUtils::toDBDateFormat($startDt);
            $this->entry->end_datetime = CalendarUtils::toDBDateFormat($endDt);
        } else {
            $this->entry->start_datetime = CalendarUtils::translateToSystemTimezone($startDt, $this->timeZone);
            $this->entry->end_datetime = CalendarUtils::translateToSystemTimezone($endDt, $this->timeZone);
        }
    }

    /**
     * @param array $data
     * @param null $formName
     * @return bool
     * @throws \Throwable
     */
    public function load($data, $formName = null)
    {
        if(empty($data)) {
            return false;
        }

        if (parent::load($data) && !empty($this->timeZone)) {
            $this->entry->time_zone = $this->timeZone;
        }

        $this->entry->content->visibility = $this->is_public;

        $result = $this->entry->load($data);

        if (empty($this->type_id)) {
            $this->type_id = null;
        }

        // Single day events are submitted as start = end without time
        $startDT = $this->getStartDateTime();
        $endDt = $this->getEndDateTime();

        if($this->entry->isAllDay() && $startDT == $endDt) {
            CalendarUtils::ensureAllDay($startDT, $endDt);
        }

        // Translate from 01.01.20 -> db date format
        $this->setFormDates($startDT, $endDt);

        $result |= $this->reminderSettings->load($data);
        $result |= $this->recurrenceForm->load($data);

        return (bool) $result;
    }

    /**
     * @return bool
     * @throws \Throwable
     */
    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        // The form expects user time zone, so we translate back from app to user timezone
        //$this->translateDateTimes($this->entry->start_datetime, $this->entry->end_datetime, Yii::$app->timeZone, $this->timeZone);

        return CalendarEntry::getDb()->transaction(function ($db) {

            if ($this->entry->save()) {
                RichText::postProcess($this->entry->description, $this->entry);
                RichText::postProcess($this->entry->participant_info, $this->entry);

                if ($this->type_id !== null) {
                    $this->entry->setType($this->type_id);
                }

                if ($this->sendUpdateNotification && !$this->entry->isNewRecord) {
                    $this->entry->participation->sendUpdateNotification();
                }

                if ($this->forceJoin) {
                    $this->entry->participation->addAllUsers();
                }

                Topic::attach($this->entry->content, $this->topics);

                return $this->reminderSettings->save() && $this->recurrenceForm->save($this->original);
            }

            return false;
        });
    }

    public static function getParticipationModeItems()
    {
        return [
            CalendarEntry::PARTICIPATION_MODE_NONE => Yii::t('CalendarModule.views_entry_edit', 'No participants'),
            CalendarEntry::PARTICIPATION_MODE_ALL => Yii::t('CalendarModule.views_entry_edit', 'Everybody can participate')
        ];
    }

    public function showTimeFields()
    {
        return !$this->entry->all_day;
    }

    public function isAllDay()
    {
        return $this->entry->all_day;
    }

    public function getStartDateTime()
    {
        $timeZone = $this->isAllDay() ? 'UTC' : $this->timeZone;
        return CalendarUtils::parseDateTimeString($this->start_date, $this->start_time, null, $timeZone);
    }

    public function getEndDateTime()
    {
        $timeZone = $this->isAllDay() ? 'UTC' : $this->timeZone;
        return CalendarUtils::parseDateTimeString($this->end_date, $this->end_time, null, $timeZone);
    }

    public function getTimeFormat()
    {
        return Yii::$app->formatter->isShowMeridiem() ? CalendarUtils::TIME_FORMAT_SHORT_MERIDIEM : CalendarUtils::TIME_FORMAT_SHORT;
    }
}