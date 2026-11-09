@extends('layouts.app')

@section('content')
<!-- ===== Main Content Start ===== -->
<main>
  <div>
    <!-- Breadcrumb Start -->
    <div x-data="{ pageName: 'Images' }">
      @include('partials.breadcrumb')
    </div>
    <!-- Breadcrumb End -->

    <div class="space-y-5 sm:space-y-6">
      <!-- Responsive Image Section -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-6 py-5">
          <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
            Responsive image
          </h3>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-gray-800 sm:p-6">
          @include('images.grid-image.image-01')
        </div>
      </div>

      <!-- 2 Grid Image Section -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-6 py-5">
          <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
            Image in 2 Grid
          </h3>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-gray-800 sm:p-6">
          @include('images.grid-image.image-02')
        </div>
      </div>

      <!-- 3 Grid Image Section -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-6 py-5">
          <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
            Image in 3 Grid
          </h3>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-gray-800 sm:p-6">
          @include('imags.grid-image.image-03')
        </div>
      </div>
    </div>
  </div>
</main>
<!-- ===== Main Content End ===== -->
@endsection