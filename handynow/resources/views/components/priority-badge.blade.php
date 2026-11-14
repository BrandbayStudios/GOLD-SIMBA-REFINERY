@props(['priority'])
@if($priority === 'emergency')
    <span class="badge bg-red-600 text-white"><x-icon name="siren" class="h-3 w-3" /> Emergency</span>
@elseif($priority === 'urgent')
    <span class="badge badge-orange">Urgent</span>
@else
    <span class="badge badge-grey">Normal</span>
@endif
