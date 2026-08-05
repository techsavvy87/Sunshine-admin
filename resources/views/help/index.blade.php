@extends('layouts.main')
@section('title', 'Help & Support')

@section('page-css')
<style>
  .help-card {
    transition: all 0.3s ease;
  }
  .help-card:hover {
    transform: translateY(-2px);
  }
  .help-card:hover .help-card-arrow {
    transform: translateX(0);
    opacity: 1;
  }
  .help-card-arrow {
    transform: translateX(-10px);
    opacity: 0;
    transition: all 0.3s ease;
  }
</style>
@endsection

@section('content')
<div class="bg-primary/10 rounded-box relative w-full overflow-hidden p-6">
  <div class="flex justify-between">
    <div>
      <div class="flex items-center gap-1">
        <p class="text-base-content/80 text-sm">Pages</p>
        <span class="iconify lucide--chevron-right text-base-content/80 size-3.5"></span>
        <p class="text-sm">Support</p>
      </div>
      <p class="text-primary mt-4 text-xl font-medium">Get Help</p>
      <p class="text-base-content/80">
        Find answers to common questions and learn how to use the platform effectively
      </p>
    </div>
  </div>
  <span class="iconify lucide--badge-help text-primary/5 absolute start-1/2 -bottom-12 size-44 -rotate-25"></span>
</div>

<div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
  <a href="{{ route('help.detail', 'getting-started') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Getting Started</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Learn how to set up your account and navigate the dashboard.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'dashboard') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Dashboard</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Understand your business overview, revenue statistics, and daily operations.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'system-settings') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">System Settings</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Manage user roles, permissions, holidays, and system configuration settings.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'customers') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Customers</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Manage customer information, additional owners, and cancellation history.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'pets') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Pets</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Register pets, manage profiles, questionnaires, and behavioral assessments.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'inventory') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Inventory</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Organize and manage inventory items, categories, and transactions.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'services') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Services</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Define services, create packages, and manage group classes.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'time-slots') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Time Slots</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Generate and manage available booking times for services.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'appointments') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Appointments</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Schedule and manage appointments throughout their lifecycle.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'service-dashboard') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Service Dashboard</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Manage service-specific appointments with Kanban board and workflow tracking.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'archives') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Archives</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        View historical appointments and generate service reports.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'incident-reports') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Incident Reports</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Document incidents, accidents, and special occurrences during service delivery.
      </p>
    </div>
  </a>

  <a href="{{ route('help.detail', 'notifications') }}" class="card card-border bg-base-100 hover:bg-primary hover:text-primary-content group hover:border-primary cursor-pointer transition-all help-card">
    <div class="card-body">
      <div class="flex justify-between">
        <p class="font-medium">Notifications</p>
        <span class="iconify lucide--arrow-right text-primary-content help-card-arrow"></span>
      </div>
      <p class="mt-1 line-clamp-2 text-sm overflow-ellipsis">
        Stay updated on important events, reminders, and system notifications.
      </p>
    </div>
  </a>
</div>

<p class="mt-12 text-center text-2xl font-medium">Frequently Asked Questions</p>
<p class="text-base-content/80 text-center">
  Find answers to common questions about system features, settings, and troubleshooting.
</p>

<div class="mt-8 grid gap-8 md:grid-cols-2">
  <div class="card bg-base-100 h-fit shadow">
    <div class="card-body pb-0">
      <div class="badge badge-sm badge-ghost">Control</div>
      <p class="text-lg font-medium">User Management queries</p>
      <div class="-mx-4 mt-2">
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">How do I add a new user?</div>
          <div class="collapse-content">
            Go to System Settings → Users, click "New" or "Add", fill in account information (username, email, password), basic information, and assign roles. Click "Save" to create the user.
          </div>
        </div>
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">How can I change a user's role?</div>
          <div class="collapse-content">
            Navigate to System Settings → Users, select the user, click "Edit", and update their assigned roles in the "Assign Roles" dropdown. You can select multiple roles. Save changes to update.
          </div>
        </div>
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">Can I deactivate a user instead of deleting them?</div>
          <div class="collapse-content">
            Yes, you can disable a user account by unchecking "Is Active" in the user settings. This prevents login but preserves all data and historical records.
          </div>
        </div>
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">What is the difference between Email Verified and Is Active?</div>
          <div class="collapse-content">
            Email Verified indicates whether the email address has been confirmed. Is Active controls whether the user can log into the system. A user can be active without email verification, but email verification is recommended for security.
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 h-fit shadow">
    <div class="card-body pb-0">
      <div class="badge badge-sm badge-ghost">Platform</div>
      <p class="text-lg font-medium">System related queries</p>
      <div class="-mx-4 mt-2">
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">How do I create a role and assign permissions?</div>
          <div class="collapse-content">
            Go to System Settings → Roles, click "Add", enter a role title and description. Then click the role to expand it, click "+ Add Permission To This Role", select a permission, check the actions (Create, Read, Update, Delete), and save.
          </div>
        </div>
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">How do I generate time slots for a service?</div>
          <div class="collapse-content">
            Go to Time Slots, select a service, choose a date, click "Generate", select a start date and end date (up to 12 months), review holidays in the period, and click "Confirm" to generate time slots.
          </div>
        </div>
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">What happens when I restrict bookings on a holiday?</div>
          <div class="collapse-content">
            When "Restrict Bookings" is enabled for a holiday, time slots will not be generated for that date. This prevents any appointments from being scheduled on that holiday.
          </div>
        </div>
        <div class="collapse-plus collapse">
          <input type="radio" aria-label="Accordion radio" class="cursor-pointer" name="accordion" />
          <div class="collapse-title cursor-pointer">How do I check in a customer and pet?</div>
          <div class="collapse-content">
            Go to Service Dashboard, select the service, find the appointment in "Scheduled" column, click on it, record check-in time, assign staff, set estimated price and pickup time, add notes, and click "Confirm Check-In" to move to "In Progress" (or "On Property" for boarding/daycare).
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
