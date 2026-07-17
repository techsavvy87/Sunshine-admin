<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public static function generateInvoiceNumber()
    {
        $latestInvoice = self::latest()->first();
        if ($latestInvoice) {
            $lastNumber = intval(substr($latestInvoice->invoice_number, 3));
            return 'INV' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        }
        return 'INV000001';
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
    }

    public function paymentLinks()
    {
        return $this->hasMany(PaymentLink::class, 'invoice_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id', 'id');
    }
}
