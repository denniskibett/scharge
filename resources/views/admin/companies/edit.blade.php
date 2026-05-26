{{-- resources/views/admin/companies/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit ' . $company->name)

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Company: {{ $company->name }}</h1>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="p-6">
            <form action="{{ route('admin.companies.update', $company) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company Name *</label>
                        <input type="text" name="name" value="{{ old('name', $company->name) }}" class="form-input w-full rounded-lg border-gray-300" required>
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Registration Number</label>
                        <input type="text" name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" class="form-input w-full rounded-lg border-gray-300">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id', $company->tax_id) }}" class="form-input w-full rounded-lg border-gray-300">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                        <input type="email" name="email" value="{{ old('email', $company->email) }}" class="form-input w-full rounded-lg border-gray-300">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" class="form-input w-full rounded-lg border-gray-300">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                        <select name="is_active" class="form-select w-full rounded-lg border-gray-300">
                            <option value="1" {{ $company->is_active ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !$company->is_active ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address</label>
                        <textarea name="address" rows="3" class="form-textarea w-full rounded-lg border-gray-300">{{ old('address', $company->address) }}</textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.companies.show', $company) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update Company</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection