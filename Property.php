<?php

namespace App\Models;

use App\Enums\DealStatuses;
use App\Enums\ParametersEnum;
use App\Enums\PropertyAttachmentTypes;
use App\Enums\PropertyStatuses;
use App\Enums\PropertyTypes;
use App\Models\Property\PropertyOwner;
use App\Models\Property\PropertyParameter;
use App\Models\Fias\Address;
use App\Models\Fias\House;
use App\Models\TypeLibraries\UsePurpose;
use App\Support\Parameterizable;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;


/**
 * Пример модели
 */

class Property extends Model
{
    use SoftDeletes, PublicId, Parameterizable;

    protected $table = 'properties';

    protected $attributes = [
    ];

    protected $fillable = [
        'title', 'user_id', 'is_examined', 'status', 'user_role', 'use_purpose', 'type', 'subtype', 'region', 'locality', 'street', 'house_num', 'building_num', 'index', 'show_street', 'cost', 'encumbrances'
    ];

    /**
     * Связь с привязанными файлами
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'model');
    }

    /**
     * Связь с собственниками
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function propertyOwners()
    {
        return $this->hasMany(PropertyOwner::class);
    }

    public function hasClientById($id)
    {
        return $this->getPropertyOwnerByClientId($id) != null;
    }

    /**
     * Связь с фотографиями
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function photos()
    {
        return $this->attachments()->where('type', '=', PropertyAttachmentTypes::PHOTO)->orderBy('sort', 'asc');
    }


    /**
     * Связь с планом помещения
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function plans()
    {
        return $this->attachments()->where('type', '=', PropertyAttachmentTypes::FLOOR_PLAN);
    }


    /**
     * Связь с документами
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function documents()
    {
        return $this->attachments()->where('type', '=', PropertyAttachmentTypes::DOCUMENT);
    }


    /**
     * Документы
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function supportingDocuments()
    {
        return $this->attachments()->where('type', '=', PropertyAttachmentTypes::SUPPORTING_DOCUMENT);
    }


    /**
     * Свзяь с таблицей параемтров
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parameters()
    {
        return $this->hasMany(PropertyParameter::class);
    }

    /**
     * Свзяь пользователем создателя объекта
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }



    public function regionFias()
    {
        return $this->belongsTo(Address::class, 'region', 'AOGUID');
    }

    public function formatRegionName()
    {
        $region = $this->regionFias;
        return ($region ? $region->formatRegionName() : '');
    }

    public function cityFias()
    {
        return $this->belongsTo(Address::class, 'locality', 'AOGUID');
    }

    public function formatCityName()
    {
        $city = $this->cityFias;
        return ($city ? $city->formatName() : '');
    }

    public function streetFias()
    {
        return $this->belongsTo(Address::class, 'street', 'AOGUID');
    }

    public function formatStreetName()
    {
        $street = $this->streetFias;
        return ($street ? $street->formatName() : '');
    }

    public function houseFias()
    {
        return $this->belongsTo(House::class, 'house_num', 'HOUSEID');
    }

    public function formatHouseName()
    {
        $house = $this->houseFias;
        return ($house ? $house->HOUSENUM : '');
    }

    public function formatAddressFias()
    {
        $arrAddress = [];
        $arrAddress[] = $this->formatRegionName();
        if($this->region != $this->locality)  $arrAddress[] = $this->formatCityName();
        if (!($this->show_street)) {
            $arrAddress[] = $this->formatStreetName();
            $arrAddress[] = $this->formatHouseName();
        }
        $arrAddress = array_filter($arrAddress);
        $address = implode(', ', $arrAddress);

        return $address;
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'property_id');
    }

    public function ads(){
        return $this->hasMany(Ads::class, 'property_id');
    }

    public function getPropertyOwnerByClientId($id)
    {
        return $this->propertyOwners()->where('client_id', '=', $id)->first();
    }

    public function getParameterByName($name)
    {
        return $this->parameters()->where('name', '=', $name)->first();
    }

}
