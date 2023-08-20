/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

humhub.module('calendar', function (module, require, $) {
        var Widget = require('ui.widget').Widget;
        var client = require('client');
        var util = require('util');
        var object = util.object;
        var modal = require('ui.modal');
        var action = require('action');
        var Content = require('content').Content;
        var event = require('event');
        var StreamEntry = require('stream').StreamEntry;

        var Calendar = Widget.extend();
        var Form = Widget.extend();

        Form.RECUR_EDIT_MODE_CREATE = 0;
        Form.RECUR_EDIT_MODE_THIS = 1;
        Form.RECUR_EDIT_MODE_FOLLOWING = 2;
        Form.RECUR_EDIT_MODE_ALL = 3;

        Form.prototype.init = function () {
            var startPrefix = '#calendarentryform-start_';
            var endPrefix = '#calendarentryform-end_';

            modal.global.$.find('.tab-basic').on('shown.bs.tab', function (e) {
                $('#calendarentry-title').focus();
            });

            modal.global.$.find('.tab-participation').on('shown.bs.tab', function (e) {
                $('#calendarentry-participation_mode').focus();
            });

            function getTimeParams() {
                return {
                    startDate: $(startPrefix + 'date').datepicker('getDate').getTime(),
                    endDate: $(endPrefix + 'date').datepicker('getDate').getTime(),
                    arrStart: $(startPrefix + 'time').val().split(':'),
                    arrEnd: $(endPrefix + 'time').val().split(':')
                }
            }

            function isNeedChangeTime(arrStart, arrEnd) {
                return arrEnd[0] * 1 <= arrStart[0] * 1 &&
                    (arrEnd[1].includes('A') && (arrStart[1].includes('A') || arrStart[1].includes('P'))) ||
                    (arrEnd[1].includes('P') && arrStart[1].includes('P'));
            }

            function getNewAmPm(time, condition) {
                if (condition) {
                    return time.includes('A') ? time.replace('A', 'P') : time.replace('P', 'A');
                }
                return time.includes('A') ? time.replace('P', 'A') : time.replace('A', 'P');
            }

            function changeTime(prefix, arrTime) {
                arrTime[0] = (arrTime[0] <= 9 ? '0' : '') + arrTime[0];

                $(prefix + 'time').val(arrTime.join(':'));
            }

            this.$.find(startPrefix + 'time').on('change', function () {
                var p = getTimeParams();
                var startDate = p.startDate;
                var endDate = p.endDate;
                var arrStart = p.arrStart;
                var arrEnd = p.arrEnd;

                if (startDate === endDate) {
                    if (arrStart[1].includes('M')) {
                        if (arrEnd[0] === '11' && arrEnd[1].includes('P')) {
                        } else if (isNeedChangeTime(arrStart, arrEnd)) {
                            arrEnd[0] = arrStart[0] * 1 === 12 ? 1 : (arrStart[0] * 1) + 1;
                            arrEnd[1] = getNewAmPm(arrStart[1], arrStart[0] * 1 === 11);
                            changeTime(endPrefix, arrEnd);
                        }
                    } else if (arrEnd[0] * 1 <= arrStart[0] * 1) {
                        arrEnd[0] = arrStart[0] === '23' ? arrEnd[0] !== '23' ? 0 : arrEnd[0] * 1 : (arrStart[0] * 1) + 1;
                        changeTime(endPrefix, arrEnd);
                    }
                }
            });

            this.$.find(endPrefix + 'time').on('change', function () {
                var p = getTimeParams();
                var startDate = p.startDate;
                var endDate = p.endDate;
                var arrStart = p.arrStart;
                var arrEnd = p.arrEnd;

                if (startDate === endDate) {
                    if (arrStart[1].includes('M')) {
                        if (arrStart[0] === '12' && arrStart[1].includes('A')) {
                        } else if (isNeedChangeTime(arrStart, arrEnd)) {
                            arrStart[0] = arrEnd[0] * 1 === 1 ? 12 : (arrEnd[0] * 1) - 1;
                            arrStart[1] = getNewAmPm(arrEnd[1], arrEnd[0] * 1 === 12);
                            changeTime(startPrefix, arrStart);
                        }
                    } else if (arrEnd[0] * 1 <= arrStart[0] * 1) {
                        arrStart[0] = arrEnd[0] * 1 === 0 ? arrStart[0] * 1 !== 0 ? 23 : arrStart[0] * 1 : (arrEnd[0] * 1) - 1;
                        changeTime(startPrefix, arrStart);
                    }
                }
            });

            this.$.find(startPrefix + 'date').on('change', function () {
                var startDate = $(startPrefix + 'date').datepicker('getDate').getTime();
                var endDate = $(endPrefix + 'date').datepicker('getDate').getTime();

                if (endDate < startDate) {
                    $(endPrefix + 'date').val($(startPrefix + 'date').val());
                }
            });

            this.$.find(endPrefix + 'date').on('change', function () {
                var startDate = $(startPrefix + 'date').datepicker('getDate').getTime();
                var endDate = $(endPrefix + 'date').datepicker('getDate').getTime();

                if (endDate < startDate) {
                    $(startPrefix + 'date').val($(endPrefix + 'date').val())
                }
            });

            this.initTimeInput();
            this.initSubmitAction();
        };

        Form.prototype.setEditMode = function (evt) {
            var mode = evt.$trigger.data('editMode');

            if (mode == Form.RECUR_EDIT_MODE_THIS) {
                $('.field-calendarentryform-is_public').hide();
            } else {
                $('.field-calendarentryform-is_public').show();
            }

            this.$.find('.calendar-edit-mode-back').show();
            this.$.find('.recurrence-edit-type').hide();
            this.$.find('.calendar-entry-form-tabs').show();
            this.$.find('#recurrenceEditMode').val(mode);
        };

        Form.prototype.showEditModes = function (evt) {
            this.$.find('.calendar-edit-mode-back').hide();
            this.$.find('.recurrence-edit-type').show();
            this.$.find('.calendar-entry-form-tabs').hide();
        };

        Form.prototype.initTimeInput = function (evt) {
            var $timeFields = modal.global.$.find('.timeField');
            var $timeInputs = $timeFields.find('.form-control');
            $timeInputs.each(function () {
                var $this = $(this);
                if ($this.prop('disabled')) {
                    $this.data('oldVal', $this.val()).val('');
                }
            });
        };

        Form.prototype.initSubmitAction = function () {
            modal.global.$.one('submitted', onCalEntryFormSubmitted);
        }

        var onCalEntryFormSubmitted = function (evt, response) {
            if (response.id) {
                modal.global.$.one('hidden.bs.modal', function () {
                    var entry = StreamEntry.getNodeByKey(response.id);
                    if (entry.length) {
                        entry = new StreamEntry(entry);
                        entry.reload();
                    }
                });
            }

            if (response.reloadWall) {
                event.trigger('humhub:content:newEntry', response.content, this);
                event.trigger('humhub:content:afterSubmit', response.content, this);
            } else {
                modal.global.$.one('submitted', onCalEntryFormSubmitted);
            }
        };

        Form.prototype.toggleDateTime = function (evt) {
            var $timeFields = modal.global.$.find('.timeField');
            var $timeInputs = $timeFields.find('.form-control');
            var $timeZoneInput = modal.global.$.find('.timeZoneField');
            if (evt.$trigger.prop('checked')) {
                $timeInputs.prop('disabled', true);
                $timeInputs.each(function () {
                    $(this).data('oldVal', $(this).val()).val('');
                });
                $timeFields.css('opacity', '0.2');
                $timeZoneInput.hide();

            } else {
                $timeInputs.each(function () {
                    var $this = $(this);
                    if ($this.data('oldVal')) {
                        $this.val($this.data('oldVal'));
                    }
                });
                $timeInputs.prop('disabled', false);
                $timeFields.css('opacity', '1.0');
                $timeZoneInput.show();
            }
        };

        Form.prototype.changeTimezone = function (evt) {
            var $dropDown = this.$.find('.timeZoneInput');
            this.$.find('.calendar-timezone').text($dropDown.find('option:selected').text());
            $dropDown.hide();
        };

        Form.prototype.toggleTimezoneInput = function (evt) {
            this.$.find('.timeZoneInput').fadeToggle();
        };

        Form.prototype.changeEventType = function (evt) {
            var $selected = evt.$trigger.find(':selected');
            if ($selected.data('type-color')) {
                $('.colorpicker-element').data('colorpicker').color.setColor($selected.data('type-color'));
                $('.colorpicker-element').data('colorpicker').update();
            }
        };

        Form.prototype.toggleRecurring = function (evt) {
            $('.calendar-entry-form-tabs .tab-recurrence').parent().toggle(evt.$trigger.is(':checked'));
        };

        Form.prototype.toggleReminder = function (evt) {
            $('.calendar-entry-form-tabs .tab-reminder').parent().toggle(evt.$trigger.is(':checked'));
        };

        var CalendarEntry = Content.extend();

        CalendarEntry.prototype.toggleClose = function (event) {
            this.update(client.post(event));
        };

        CalendarEntry.prototype.reload = function (event) {
            return this.parent().reload();
        };

        CalendarEntry.prototype.update = function (update) {
            this.loader();
            update.then($.proxy(this.handleUpdateSuccess, this))
                .catch(CalendarEntry.handleUpdateError)
                .finally($.proxy(this.loader, this, false));
        };

        CalendarEntry.prototype.loader = function ($loader) {
            this.streamEntry().loader($loader);
        };

        CalendarEntry.prototype.handleUpdateSuccess = function (response) {
            var streamEntry = this.streamEntry();
            return streamEntry.replace(response.output).catch(function (e) {
                module.log.error(e, true);
            });
        };

        CalendarEntry.handleUpdateError = function (e) {
            module.log.error(e, true);
        };

        CalendarEntry.prototype.streamEntry = function () {
            return this.parent();
        };

        /**
         * Action respond to calendar entry (participation)
         * @param evt
         */
        var respond = function (evt) {
            evt.block = action.BLOCK_MANUAL;
            client.post(evt).then(function (response) {
                if (response.success) {
                    var entry = Widget.closest(evt.$trigger);
                    entry.reload().then(function () {
                        module.log.success('saved');
                    });
                } else {
                    module.log.error(e, true);
                    evt.finish();
                }
            }).catch(function (e) {
                module.log.error(e, true);
                evt.finish();
            });
        };

        var editModal = function (evt) {
            var streamEntry = Widget.closest(evt.$trigger);
            streamEntry.loader();
            modal.load(evt).then(function (response) {
                modal.global.$.one('submitted', function () {
                    var calendar = getCalendar();
                    if (calendar) {
                        calendar.fetch();
                    }
                });
            }).catch(function (e) {
                module.log.error(e, true);
            });
        };

        var getCalendar = function () {
            return Widget.instance('#calendar');
        };

        var deleteEvent = function (evt) {
            var streamEntry = Widget.closest(evt.$trigger);
            streamEntry.loader();
            modal.confirm().then(function (confirm) {
                if (confirm) {
                    client.post(evt).then(function () {
                        modal.global.close();
                    }).catch(function (e) {
                        module.log.error(e, true);
                    });
                } else {
                    var streamEntry = Widget.closest(evt.$trigger);
                    streamEntry.loader(false);
                }
            }).finally(function () {
                evt.finish();
            });
        };

        module.export({
            Calendar: Calendar,
            respond: respond,
            editModal: editModal,
            deleteEvent: deleteEvent,
            CalendarEntry: CalendarEntry,
            Form: Form
        });
    }
);
