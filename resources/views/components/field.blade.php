@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
<div>
    @if($label)<label for="f-{{ $name }}" class="label">{{ $label }} @if($required)<span class="text-brand-600">*</span>@endif</label>@endif
    @if($type === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" {{ $required ? 'required' : '' }} {{ $attributes->merge(['class' => 'input '.($errors->has($name) ? 'input-error' : ''), 'rows' => 4]) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" @if($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif {{ $required ? 'required' : '' }} {{ $attributes->merge(['class' => 'input '.($errors->has($name) ? 'input-error' : '')]) }}>
    @endif
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
