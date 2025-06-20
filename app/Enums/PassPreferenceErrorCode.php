<?php

namespace App\Enums;

enum PassPreferenceErrorCode: string
{
    // General Errors
    case INVALID_REQUEST_BODY = 'INVALID_REQUEST_BODY';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case AUTHORIZATION_FAILED = 'AUTHORIZATION_FAILED';
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND'; // Generic not found

    // Pass Preference Specific Business Logic Errors
    case VAAZ_CENTER_EVENT_MISMATCH = 'VAAZ_CENTER_EVENT_MISMATCH';
    case BLOCK_EVENT_MISMATCH = 'BLOCK_EVENT_MISMATCH';
    case BLOCK_VAAZ_CENTER_MISMATCH = 'BLOCK_VAAZ_CENTER_MISMATCH';
    case VAAZ_CENTER_FULL = 'VAAZ_CENTER_FULL';
    case BLOCK_FULL = 'BLOCK_FULL';
    case ITS_ID_ALREADY_EXISTS_FOR_EVENT = 'ITS_ID_ALREADY_EXISTS_FOR_EVENT'; // For unique constraint on its_id + event_id

    // Gender-specific capacity errors
    case VAAZ_CENTER_CAPACITY_GENDER_UNAVAILABLE = 'VAAZ_CENTER_CAPACITY_GENDER_UNAVAILABLE'; // e.g. No male_capacity defined
    case VAAZ_CENTER_CAPACITY_GENDER_UNSUPPORTED = 'VAAZ_CENTER_CAPACITY_GENDER_UNSUPPORTED'; // e.g. Gender is 'other', but only male/female capacities exist

    // Unknown/Generic Server Error
    case UNKNOWN_ERROR = 'UNKNOWN_ERROR';
}
