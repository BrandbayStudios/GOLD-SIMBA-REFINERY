@extends('layouts.dashboard', ['title' => 'Add administrator'])
@section('content')
<form method="POST" action="{{ route('admin.users.store') }}" class="card card-pad max-w-xl space-y-4">
    @csrf
    <x-field name="name" label="Full name" :required="true" />
    <x-field name="email" type="email" label="Email" :required="true" />
    <x-field name="phone" type="tel" label="Phone" :required="true" />
    <x-field name="password" type="password" label="Temporary password" :required="true" hint="At least 10 characters with letters and numbers." />
    <button class="btn btn-primary">Create administrator</button>
</form>
@endsection
