<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::livewire('/', 'admin::-dashboard')->name('admin.dashboard');

Route::livewire('/records', 'admin::records.index')->name('admin.records.index');
Route::livewire('/records/{record}', 'admin::records.show')->name('admin.records.show');

Route::livewire('/plotter', 'admin::plotter.index')->name('admin.plotter.index');

Route::livewire('/record-types', 'admin::record-types.index')->name('admin.record-types.index');

Route::livewire('/owner-types', 'admin::owner-types.index')->name('admin.owner-types.index');

Route::livewire('/locations', 'admin::locations.index')->name('admin.locations.index');

Route::livewire('/stations', 'admin::stations.index')->name('admin.stations.index');
