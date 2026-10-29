<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::livewire('/', 'admin::-dashboard')->name('admin.dashboard');

Route::livewire('/records', 'admin::records.index')->name('admin.records.index');
Route::livewire('/records/{record}', 'admin::records.show')->name('admin.records.show');

Route::livewire('/plotter', 'admin::plotter.index')->name('admin.plotter.index');

/*
 * Everything configurable lives under /settings, so the address bar says where
 * you are: the landing page plus one child per section. The route names are
 * unchanged, so every route() call in the app follows the new paths.
 */
Route::livewire('/settings', 'admin::settings.index')->name('admin.settings.index');

Route::livewire('/settings/imports', 'admin::import.index')->name('admin.import.index');

Route::livewire('/settings/record-types', 'admin::record-types.index')->name('admin.record-types.index');

Route::livewire('/settings/owner-types', 'admin::owner-types.index')->name('admin.owner-types.index');

Route::livewire('/settings/locations', 'admin::locations.index')->name('admin.locations.index');

Route::livewire('/settings/stations', 'admin::stations.index')->name('admin.stations.index');
