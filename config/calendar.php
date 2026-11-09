<?php

// /calendar: when things are due. See App\Http\Controllers\CalendarController.
return [
    // Rent / service-charge invoices are due on this day of their billing month.
    'rent_due_day' => (int) env('CALENDAR_RENT_DUE_DAY', 5),

    // Meter readings are taken from the 1st up to this day; bills go out the day after.
    'meter_reading_due_day' => (int) env('CALENDAR_METER_READING_DUE_DAY', 5),
];
