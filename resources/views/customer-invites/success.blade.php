@extends('layouts.public_portal')
@section('title', 'Registration Complete')

@section('content')
<div class="mx-auto mt-8 max-w-3xl">
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body items-center text-center">
      <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-success/10 text-success">
        <span class="iconify lucide--check size-9"></span>
      </div>

      <h3 class="text-2xl font-semibold">Registration Complete</h3>

      <p class="text-base-content/70 mt-2 max-w-xl text-sm">
        Thank you. Your account and pet profile(s) have been created successfully for
        <span class="font-medium text-base-content">{{ $email }}</span>.
      </p>

      <div class="divider my-4"></div>

      <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3">
        <div class="rounded-box bg-base-200 p-4">
          <div class="flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#167bff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-check-icon lucide-user-round-check"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="m16 19 2 2 4-4"/></svg>
          </div>
          <p class="font-medium">Account Created</p>
        </div>

        <div class="rounded-box bg-base-200 p-4">
          <div class="flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#167bff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dog-icon lucide-dog"><path d="M11.25 16.25h1.5L12 17z"/><path d="M16 14v.5"/><path d="M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444a11.702 11.702 0 0 0-.493-3.309"/><path d="M8 14v.5"/><path d="M8.5 8.5c-.384 1.05-1.083 2.028-2.344 2.5-1.931.722-3.576-.297-3.656-1-.113-.994 1.177-6.53 4-7 1.923-.321 3.651.845 3.651 2.235A7.497 7.497 0 0 1 14 5.277c0-1.39 1.844-2.598 3.767-2.277 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.855-1.45-2.239-2.5"/></svg>
          </div>
          <p class="font-medium">Pet Profile Saved</p>
        </div>

        <div class="rounded-box bg-base-200 p-4">
          <span class="iconify lucide--shield-check mx-auto mb-2 size-6 text-primary"></span>
          <p class="font-medium">Information Submitted</p>
        </div>
      </div>

      <p class="text-base-content/60 mt-5 text-sm">
        Our staff will review your information and contact you if anything else is needed.
      </p>
    </div>
  </div>
</div>
@endsection