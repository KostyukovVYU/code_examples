<?php

namespace App\Manager;

use App\Enums\PropertyAttachmentTypes;
use App\Enums\PropertyParamGroups;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Facades\GeoFacade;

/**
 * Сохранение сущность и ее параметров
 */
class PropertyEditManager
{
    public function getEditableObjectData(Property $property)
    {
        $data = collect([]);
        $data = $data->merge($property->getAttributes());

        $property->parameters->each(function ($item) use ($data) {
            $typeData = $data->get($item->type, collect())->put($item->name, $item->value);
            $data->put($item->type, $typeData);
        });
        return $data;
    }

    public function getPropertyData(Property $property)
    {
        $data = collect([]);
        $data = $data->merge($property->getAttributes());

        $property->parameters->each(function ($item) use ($data) {
            $typeData = $data->get($item->type, collect())->put($item->name, $item->value);
            $data->put($item->type, $typeData);
        });
        return $data;
    }

    public function updateProperty(Property $property, array $data)
    {
        $property->user()->associate(Auth::user());
        $property->user_role = Arr::get($data, 'role');
        $property->use_purpose = Arr::get($data, 'use_purpose');
        $property->type = Arr::get($data, 'main_type');
        $property->subtype = Arr::get($data, 'sub_type');
        $property->title = Arr::get($data, 'title');
        $property->region = Arr::get($data, 'region');
        $property->index = Arr::get($data, 'index');
        $property->locality = Arr::get($data, 'locality');
        $property->street = Arr::get($data, 'street');
        $property->house_num = Arr::get($data, 'house_num');
        $property->building_num = Arr::get($data, 'building_num');
        $property->show_street = boolval(Arr::get($data, 'show_street'));
        $property->cost = Arr::get($data, 'cost');
        $property->encumbrances = Arr::get($data, 'encumbrances');
        $property->save();

        $property = $this->updatePropertyAdditional($property, $data);

        return $property;
    }

    private function compileParams($paramInfo, $data, $type, $propertyParamData)
    {
        foreach ($paramInfo as $item) {
            $propertyParamData[$type][$item] = Arr::get($data, $item);
        }
        return $propertyParamData;
    }

    private function syncOwners(Collection $ownerData, Property $property)
    {
        $avatarData = $ownerData->get('avatar', []);
        $clientId = $ownerData->get('client_id');
        $phones = $ownerData->get('phones');
        $phones = array_combine($phones, $phones);

        $share = $ownerData->get('share', '');
        if($share){
            $frs = explode('/', $share);
            $ownerData->put('share_numerator', (int)$frs[0]);
            $ownerData->put('share_denominator', (int)$frs[1]);
        }

        $propertyOwner = new Property\PropertyOwner($ownerData->all());
        $propertyOwner->property()->associate($property);
        if ($clientId) {
            $propertyOwner->client()->associate(Client::find($clientId));
        }

        $propertyOwner->save();

        if (!empty($avatarData)) {
            $avatar = $propertyOwner->avatar;
            $avatar->storage = array_get($avatarData, 'storage');
            $avatar->file_path = array_get($avatarData, 'file_path');
            $avatar->save();
        }

        $propertyOwner->syncOneToMany('phones', 'phone', $phones, 'phone');
    }


    public function updatePropertyAdditional(Property $property, array $data)
    {
        //Формирование адреса строкой и geo данных
        $address_str = $property->formatAddressFias();
        if($address_str) {
            $geo = GeoFacade::geoAddress($address_str);
            if($geo){
                $coords = explode(';', $geo);
                $property->geo_longitude = $coords[0];
                $property->geo_latitude = $coords[1];
            }
        }
        $property->address_str = $address_str;
        $property->save();

        $propertyParamData = [];

        // Дополнительно
        $extraInfo = config('property_parameters.extra');
        $propertyParamData = $this->compileParams($extraInfo, $data, PropertyParamGroups::EXTRA, $propertyParamData);

        // Контактная информация
        $contactInfo = config('property_parameters.contact');
        $userContacts = Arr::get($data, 'user_contacts', []);
        $propertyParamData = $this->compileParams($contactInfo, $userContacts, PropertyParamGroups::CONTACT_INFORMATION, $propertyParamData);

        // Основные данные
        $generalInfo = Arr::get(config('property_general'), $property->subtype, []);
        $general = Arr::get($data, 'general', []);
        $propertyParamData = $this->compileParams($generalInfo, $general, PropertyParamGroups::GENERAL, $propertyParamData);

        // Обустройство и удобство
        $convenienceInfo = Arr::get(config('property_convenience'), $property->subtype, []);
        $convenience = Arr::get($data, 'convenience', []);
        $propertyParamData = $this->compileParams($convenienceInfo, $convenience, PropertyParamGroups::CONVENIENCE, $propertyParamData);

        // Описание
        $descriptionInfo = Arr::get(config('property_description'), $property->subtype, []);
        $description = Arr::get($data, 'description', []);
        $propertyParamData = $this->compileParams($descriptionInfo, $description, PropertyParamGroups::DESCRIPTION, $propertyParamData);

        $property->setArrayParameters($propertyParamData);
        $property->save();

        $property->propertyOwners()->delete();
        $ownersData = Arr::get($data, 'owners', []);
        foreach ($ownersData as $owner) {
            $this->syncOwners(collect($owner), $property);
        }

        $files = Arr::get($data, 'files', []);
        $fileGroups = collect([
            PropertyAttachmentTypes::PHOTO => Arr::get($files, 'photos', []),
            PropertyAttachmentTypes::FLOOR_PLAN => Arr::get($files, 'plans', []),
            PropertyAttachmentTypes::DOCUMENT => Arr::get($files, 'documents', []),
            PropertyAttachmentTypes::SUPPORTING_DOCUMENT => Arr::get($files, 'supporting_documents', []),
        ]);

        $property->attachments()->update([
            'model_id' => null,
            'model_type' => null
        ]);

        $fileGroups->each(function ($ids, $fileGroup) use ($property) {
            $k = 1;
            foreach($ids as $id){
                Attachment::where('id', $id)
                    ->update([
                        'type' => $fileGroup,
                        'model_id' => $property->id,
                        'model_type' => $property->getMorphClass(),
                        'sort' => $k++
                    ]);
            }
        });

        return $property;

    }
}
