<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quotations:expire')->hourly()->withoutOverlapping();
