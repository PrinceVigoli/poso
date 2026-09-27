@foreach(['top_number', 'photo_token', 'license_no', 'vehicle_plate', 'vehicle_type', 'address', 'contact_no', 'birthdate', 'confiscated_id', 'additional_info'] as $field)
    <input type="hidden" name="{{ $field }}" value="{{ $inputData[$field] ?? '' }}">
@endforeach
