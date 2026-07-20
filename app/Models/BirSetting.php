<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirSetting extends Model
{
    protected $table = "bir_settings";

    protected $fillable = [
        "company_code",
        "tin",
        "rdo_code",
        "name",
        "address",
        "zip",
        "contact_number",
        "signatory_name",
        "atc",
        "is_government",
        "is_amended_return",
        "has_tax_relief",
    ];

    protected $casts = [
        "is_government" => "boolean",
        "is_amended_return" => "boolean",
        "has_tax_relief" => "boolean",
    ];

    public static function forCurrentCompany(): ?self
    {
        return static::where('company_code', env('COMPANY_NAME'))->first();
    }

    public function tinGroups(): array
    {
        return array_slice(array_pad(explode('/', (string) $this->tin), 3, ''), 0, 3);
    }
}
