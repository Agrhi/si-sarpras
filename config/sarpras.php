<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Loan reminders
    |--------------------------------------------------------------------------
    |
    | Jumlah hari sebelum jatuh tempo peminjaman saat pengingat dikirim, serta
    | apakah notifikasi keterlambatan diulang setiap hari. Bila diulang,
    | kolom overdue_reminder_sent_at hanya mencatat pengiriman terakhir.
    |
    */

    'loan_reminder_days' => (int) env('SARPRAS_LOAN_REMINDER_DAYS', 1),

    'notify_overdue_daily' => (bool) env('SARPRAS_NOTIFY_OVERDUE_DAILY', false),

    /*
    |--------------------------------------------------------------------------
    | Maintenance reminders
    |--------------------------------------------------------------------------
    |
    | Jumlah hari sebelum jatuh tempo jadwal pemeliharaan saat pengingat
    | dikirim. Pengingat hanya dikirim sekali per jadwal per hari.
    |
    */

    'maintenance_reminder_days' => (int) env('SARPRAS_MAINTENANCE_REMINDER_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Zona waktu yang dipakai scheduler pengingat.
    |
    */

    'timezone' => env('SARPRAS_TIMEZONE', 'Asia/Jakarta'),

];
