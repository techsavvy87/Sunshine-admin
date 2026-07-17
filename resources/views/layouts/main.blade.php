<!doctype html>
<html lang="en" class="group/html">
  <head>
    <title>@yield('title') - Sunshine</title>
    <meta charset="UTF-8" />
    <meta name="author" content="Denish Navadiya" />
    <meta name="keywords" content="HTML, CSS, daisyui, tailwindcss, admin, client, dashboard, ui kit, component" />
    <meta name="description" content="Start your next project with Nexus, designed for effortless customization to streamline your development process" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="{{ asset('images/favicon-dark.png') }}" media="(prefers-color-scheme: dark)" />
    <link rel="shortcut icon" href="{{ asset('images/favicon-light.png') }}" media="(prefers-color-scheme: light)" />
    @yield('page-css')
    <script>
      try {
        const localStorageItem = localStorage.getItem("__NEXUS_CONFIG_v2.0__")
        if (localStorageItem) {
          const theme = JSON.parse(localStorageItem).theme
          if (theme !== "system") {
            document.documentElement.setAttribute("data-theme", theme)
          }
        }
      } catch (err) {
        console.log(err)
      }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="{{ asset('src/assets/app.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
    <link href="{{ asset('src/assets/custom.css') }}" rel="stylesheet">
  </head>
  <body>
    <!--  Start: Layout - Main -->
    <div class="size-full">
      <div class="flex">

        <!--  Start: Layout - Sidebar -->
        <input type="checkbox" id="layout-sidebar-toggle-trigger" class="hidden" aria-label="Toggle layout sidebar" />
        <div id="layout-sidebar">
          <a class="flex min-h-16 items-center justify-center" href="{{ route('dashboard') }}">
            <img alt="logo-dark" class="hidden dark:inline" src="{{ asset('images/logo.png') }}" width="110"/>
            <img alt="logo-light" class="dark:hidden" src="{{ asset('images/logo.png') }}" width="110"/>
          </a>
          <div class="relative min-h-0 grow mt-2">
            <div data-simplebar class="size-full">
              <div id="sidebar-menu">
                <a class="sidebar-menu-item" href="{{ route('dashboard') }}">
                  <span class="iconify lucide--monitor-dot size-4"></span>
                  Dashboard
                </a>
                @php
                  $hasSystemSettingsPermission = hasPermission(6, 'can_read') || hasPermission(7, 'can_read') || hasPermission(8, 'can_read') || hasPermission(9, 'can_read') || hasPermission(25, 'can_read') || hasPermission(26, 'can_read') || hasPermission(27, 'can_read') || hasPermission(28, 'can_read');
                @endphp
                @if ($hasSystemSettingsPermission)
                <div class="group collapse">
                  <input aria-label="Sidemenu item trigger" class="peer" type="checkbox" name="sidebar-menu-parent-item" />
                  <div class="collapse-title">
                    <span class="iconify lucide--settings size-4"></span>
                    <span class="grow">System Settings</span>
                    <span class="iconify lucide--chevron-right arrow-icon size-3.5"></span>
                  </div>
                  <div class="collapse-content">
                    @if (hasPermission(26, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('facility-address') }}">Facility Address</a>
                    @endif
                    @if (hasPermission(6, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('permissions') }}">Permissions</a>
                    @endif
                    @if (hasPermission(6, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('roles') }}">Roles</a>
                    @endif
                    @if (hasPermission(7, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('holidays') }}">Holidays</a>
                    @endif
                    @if (hasPermission(8, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('weight-ranges') }}">Weight Ranges</a>
                    @endif
                    @if (hasPermission(9, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('capacities') }}">Capacities</a>
                    @endif
                    @if (hasPermission(27, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('kennels') }}">Kennels</a>
                    @endif
                    @if (hasPermission(28, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('rooms') }}">Rooms</a>
                    @endif
                    @if (hasPermission(25, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('pet-behaviors') }}">Pet Behaviors</a>
                    @endif
                  </div>
                </div>
                @endif
                @if (hasPermission(5, 'can_read'))
                <div class="group collapse">
                  <input aria-label="Sidemenu item trigger" class="peer" type="checkbox" name="sidebar-menu-parent-item" />
                  <div class="collapse-title">
                    <span class="iconify lucide--users size-4"></span>
                    <span class="grow">Users</span>
                    <span class="iconify lucide--chevron-right arrow-icon size-3.5"></span>
                  </div>
                  <div class="collapse-content">
                    <a class="sidebar-menu-item" href="{{ route('users') }}">List</a>
                    <a class="sidebar-menu-item" href="{{ route('attendance') }}">Attendance</a>
                  </div>
                </div>
                @endif
                @if (hasPermission(1, 'can_read') || hasPermission(24, 'can_read'))
                <div class="group collapse">
                  <input aria-label="Sidemenu item trigger" class="peer" type="checkbox" name="sidebar-menu-parent-item" />
                  <div class="collapse-title">
                    <span class="iconify lucide--users size-4"></span>
                    <span class="grow">Customers</span>
                    <span class="iconify lucide--chevron-right arrow-icon size-3.5"></span>
                  </div>
                  <div class="collapse-content">
                    @if (hasPermission(1, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('customers') }}">List</a>
                    @endif
                    @if (hasPermission(19, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('complaints') }}">Complaints / Issues</a>
                    @endif
                  </div>
                </div>
                @endif
                @if (hasPermission(21, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('maintenance') }}">
                  <span class="iconify lucide--paperclip size-4"></span>
                  Maintenance
                </a>
                @endif
                @if (hasPermission(2, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('pets') }}">
                  <span class="iconify lucide--brain-circuit size-4"></span>
                  Pets
                </a>
                @endif
                @php
                  $hasServicePermission = hasPermission(11, 'can_read') || hasPermission(12, 'can_read') || hasPermission(13, 'can_read');
                @endphp
                @if ($hasServicePermission)
                <div class="group collapse">
                  <input aria-label="Sidemenu item trigger" class="peer" type="checkbox" name="sidebar-menu-parent-item" />
                  <div class="collapse-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-basket-icon lucide-shopping-basket"><path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/></svg>
                    <span class="grow">Service</span>
                    <span class="iconify lucide--chevron-right arrow-icon size-3.5"></span>
                  </div>
                  <div class="collapse-content">
                    @if (hasPermission(11, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('service-categories') }}">Categories</a>
                    @endif
                    @if (hasPermission(12, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('services') }}">Services</a>
                    @endif
                    @if (hasPermission(13, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('timeslots') }}">Time Slots</a>
                    @endif
                  </div>
                </div>
                @endif
                @if (hasPermission(3, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('appointments') }}">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-clock-icon lucide-calendar-clock"><path d="M16 14v2.2l1.6 1"/><path d="M16 2v4"/><path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M3 10h5"/><path d="M8 2v4"/><circle cx="16" cy="16" r="6"/></svg>
                  Appointments
                </a>
                @endif
                @if (hasPermission(14, 'can_read') || hasPermission(14, 'can_create'))
                <div class="group collapse">
                  <input aria-label="Sidemenu item trigger" class="peer" type="checkbox" name="sidebar-menu-parent-item" />
                  <div class="collapse-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-badge-dollar-sign-icon lucide-badge-dollar-sign"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                    <span class="grow">Financials</span>
                    <span class="iconify lucide--chevron-right arrow-icon size-3.5"></span>
                  </div>
                  <div class="collapse-content">
                    <a class="sidebar-menu-item" href="{{ route('financials.payments') }}">Payments</a>
                    <a class="sidebar-menu-item" href="{{ route('financials.payouts') }}">Payouts</a>
                  </div>
                </div>
                @endif
                @php
                  $hasServiceDashboardPermission = false;
                  foreach($servicesForMenu as $service) {
                    $servicePermissionId = getServicePermissionId($service);
                    if ($servicePermissionId && hasPermission($servicePermissionId, 'can_read')) {
                      $hasServiceDashboardPermission = true;
                      break;
                    }
                  }
                  $hasServiceDashboardPermission = $hasServiceDashboardPermission || hasPermission(18, 'can_read');
                @endphp
                @if ($hasServiceDashboardPermission)
                <div class="group collapse">
                  <input aria-label="Sidemenu item trigger" class="peer" type="checkbox" name="sidebar-menu-parent-item" />
                  <div class="collapse-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-baggage-claim-icon lucide-baggage-claim"><path d="M22 18H6a2 2 0 0 1-2-2V7a2 2 0 0 0-2-2"/><path d="M17 14V4a2 2 0 0 0-2-2h-1a2 2 0 0 0-2 2v10"/><rect width="13" height="8" x="8" y="6" rx="1"/><circle cx="18" cy="20" r="2"/><circle cx="9" cy="20" r="2"/></svg>
                    <span class="grow">Service Dashboard</span>
                    <span class="iconify lucide--chevron-right arrow-icon size-3.5"></span>
                  </div>
                  <div class="collapse-content">
                    @foreach($servicesForMenu as $service)
                      @php
                        $servicePermissionId = getServicePermissionId($service);
                      @endphp
                      @if ($servicePermissionId && hasPermission($servicePermissionId, 'can_read'))
                      <a class="sidebar-menu-item" href="{{ route('service-dashboard', ['id' => $service->id]) }}">
                        {{ $service->name }}
                      </a>
                      @endif
                    @endforeach
                    @if (hasPermission(18, 'can_read'))
                    <a class="sidebar-menu-item" href="{{ route('boarding-process-log') }}">
                      Boarding Process
                    </a>
                    @endif
                  </div>
                </div>
                @endif
                @if (hasPermission(3, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('archives') }}">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive"><path d="M3 6h18l-2 13H5L3 6Z"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  Appointment Archive
                </a>
                @endif
                @if (hasPermission(22, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('notifications') }}">
                  <span class="iconify lucide--bell-dot size-4"></span>
                  Notifications
                </a>
                @endif
                @if (hasPermission(23, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('appointment-audit-log') }}">
                  <span class="iconify lucide--layers size-4"></span>
                  Audit Logs
                </a>
                @endif
                @if (hasPermission(24, 'can_read'))
                <a class="sidebar-menu-item" href="{{ route('discounts') }}">
                  <span class="iconify lucide--percent size-4"></span>
                  Discounts
                </a>
                @endif
              </div>
            </div>
            <div class="from-base-100/60 pointer-events-none absolute start-0 end-0 bottom-0 h-7 bg-linear-to-t to-transparent"></div>
          </div>
        </div>
        <label for="layout-sidebar-toggle-trigger" id="layout-sidebar-backdrop"></label>
        <!--  End: Layout - Sidebar -->

        <!--  Start: Layout - Topbar -->
        <div class="flex h-screen min-w-0 grow flex-col overflow-auto">
          <div role="navigation" aria-label="Navbar" class="flex items-center justify-between px-3" id="layout-topbar">
            <div class="inline-flex items-center gap-3 search-container">
              <label class="btn btn-square btn-ghost btn-sm" aria-label="Leftmenu toggle" for="layout-sidebar-toggle-trigger">
                <span class="iconify lucide--menu size-5"></span>
              </label>
              <select class="select w-full input-sm" name="search_pet_customer" id="search_pet_customer">
              </select>
            </div>
            <div class="inline-flex items-center gap-1.5">
              <a href="{{ route('help') }}" class="btn btn-circle btn-ghost btn-sm" aria-label="Help" title="Help & Support">
                <span class="iconify lucide--help-circle size-4.5"></span>
              </a>
              <div class="dropdown dropdown-center">
                <div tabindex="0" role="button" class="btn btn-sm btn-circle btn-ghost" aria-label="Theme toggle">
                <span class="iconify lucide--sun hidden size-4.5 group-data-[theme=light]/html:inline"></span>
                <span class="iconify lucide--moon hidden size-4.5 group-data-[theme=dark]/html:inline"></span>
                <span class="iconify lucide--monitor hidden size-4.5 group-[:not([data-theme])]/html:inline"></span>
                <span class="iconify lucide--palette hidden size-4.5 group-data-[theme=contrast]/html:inline group-data-[theme=dim]/html:inline group-data-[theme=material]/html:inline group-data-[theme=material-dark]/html:inline"></span>
              </div>
              <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-1 mt-2 w-36 space-y-0.5 p-1 shadow-sm">
                <li>
                  <div class="group-data-[theme=light]/html:bg-base-200 flex gap-2" data-theme-control="light">
                    <span class="iconify lucide--sun size-4.5"></span>
                    <span class="font-medium">Light</span>
                  </div>
                </li>
                <li>
                  <div class="group-data-[theme=dark]/html:bg-base-200 flex gap-2" data-theme-control="dark">
                    <span class="iconify lucide--moon size-4.5"></span>
                    <span class="font-medium">Dark</span>
                  </div>
                </li>
                <li>
                  <div class="group-[:not([data-theme])]/html:bg-base-200 flex gap-2" data-theme-control="system">
                    <span class="iconify lucide--monitor size-4.5"></span>
                    <span class="font-medium">System</span>
                  </div>
                </li>
              </ul>
            </div>
            <div class="dropdown dropdown-bottom sm:dropdown-end max-sm:dropdown-center">
              <div tabindex="0" role="button" class="btn btn-circle btn-ghost btn-sm" aria-label="Notifications">
                <span class="iconify lucide--bell size-4.5"></span>
                <span class="badge badge-error dots hidden" id="notification_dot"></span>
              </div>
              <div tabindex="0" class="dropdown-content bg-base-100 rounded-box card card-compact mt-5 w-60 p-2 shadow sm:w-84">
                <div class="flex items-center justify-between px-2">
                  <p class="text-base font-medium">Notification</p>
                  <button type="button" tabindex="0" class="btn btn-sm btn-circle btn-ghost" aria-label="Close" onclick="closeDropdown(this)">
                    <span class="iconify lucide--x size-4"></span>
                  </button>
                </div>
                <div style="max-height: 340px; overflow-y: auto;">
                  <div class="flex items-center justify-center">
                    <div class="badge badge-sm badge-primary badge-soft">Today</div>
                  </div>
                  <div class="mt-2" id="notification_today_container">
                  </div>
                  <div class="mt-2 flex items-center justify-center">
                    <div class="badge badge-sm">Previous</div>
                  </div>
                  <div class="mt-2" id="notification_previous_container">
                  </div>
                </div>
                <hr class="border-base-300 -mx-2 mt-2" />
                <div class="flex items-center justify-between pt-2">
                  <button class="btn btn-sm btn-ghost" onclick="handleMarkRead()" id="mark_read_btn">Mark as read</button>
                  <button class="btn btn-sm btn-soft btn-primary" onclick="viewAllNotifications()" id="view_all_btn">View All</button>
                </div>
              </div>
            </div>
            <div class="dropdown dropdown-bottom dropdown-end">
              <div tabindex="0" role="button" class="btn btn-ghost rounded-btn px-1.5">
                <div class="flex items-center gap-2">
                  <div class="avatar">
                    <div class="bg-base-200 mask mask-squircle w-8">
                      <img alt="Avatar" src='{{ asset((Auth::user()->profile && Auth::user()->profile->avatar_img) ? "storage/profiles/" . Auth::user()->profile->avatar_img : "images/default-user-avatar.png") }}' />
                    </div>
                  </div>
                </div>
              </div>
              <div class="dropdown-content bg-base-100 rounded-box mt-4 w-44 shadow" tabindex="0">
                <div class="-space-y-0.5 text-start px-4 pt-3">
                  <p class="text-sm font-bold">{{ Auth::user()->name }}</p>
                  <p class="text-base-content/60 text-xs">{{Auth::user()->email}}</p>
                </div>
                <ul class="menu w-full p-2">
                  <li>
                    <a href="/pages-settings.html">
                      <span class="iconify lucide--user size-4"></span>
                      <span>My Account</span>
                    </a>
                  </li>
                </ul>
                <hr class="border-base-300" />
                <ul class="menu w-full p-2">
                  <li>
                    <a class="text-error hover:bg-error/10" href="{{ route('logout') }}">
                      <span class="iconify lucide--log-out size-4"></span>
                      <span>Logout</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <!--  End: Layout - Topbar -->

        <!--  Start: Layout - Content -->
        <div id="layout-content">
          @yield('content')
        </div>
        <!--  End: Layout - Content -->
      </div>
      <dialog id="alert_modal" class="modal">
        <div class="modal-box">
          <div class="flex items-center justify-center">
            <span class="iconify lucide--triangle-alert size-10 text-error"></span>
          </div>
          <p class="py-4 text-center" id="alert_message"></p>
          <div class="flex items-center justify-center mt-3">
            <form method="dialog">
              <button class="btn btn-soft btn-primary">Close</button>
            </form>
          </div>
        </div>
      </dialog>
      <dialog id="success_modal" class="modal">
        <div class="modal-box">
          <div class="flex items-center justify-center">
            <span class="iconify lucide--badge-check size-10 text-success"></span>
          </div>
          <p class="py-4 text-center" id="success_message"></p>
          <div class="flex items-center justify-center mt-3">
            <form method="dialog">
              <button class="btn btn-soft btn-primary">Close</button>
            </form>
          </div>
        </div>
      </dialog>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/simplebar/6.2.7/simplebar.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simplebar/6.2.7/simplebar.css" />
    <script src="{{ asset('src/js/jquery.min.js') }}"></script>
    <script src="{{ asset('src/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('src/js/app.js') }}"></script>
    <script>
      function formatPhoneNumber(ele) {
        let value = $(ele).val().replace(/\D/g, ''); // Remove non-digits
        if (value.length > 0) {
          value = '(' + value;
        }
        if (value.length > 4) {
          value = value.slice(0, 4) + ') ' + value.slice(4);
        }
        if (value.length > 9) {
          value = value.slice(0, 9) + '-' + value.slice(9);
        }
        value = value.slice(0, 14); // Limit to (123) 456-7896
        $(ele).val(value);
      }

      $(document).ready(function() {
        // initial load
        fetchNotifications();
        // refresh every 15 seconds
        setInterval(fetchNotifications, 15000);
      });

      function fetchNotifications() {
        $.ajax({
          url: '{{ route("list-notification-user") }}',
          method: 'GET',
          dataType: 'json',
          success: function(data) {
            if (data.status) {
              if (data.result.length > 0) {
                $('#notification_dot').removeClass('hidden');

                // Prepare grouped arrays with parsed dates
                var todayArr = [];
                var previousArr = [];
                var now = new Date();

                data.result.forEach(function(item) {
                  var created = null;
                  try {
                    created = item.created_at ? new Date(item.created_at) : null;
                  } catch (e) {
                    created = null;
                  }
                  if (!created || isNaN(created.getTime())) {
                    // if no valid created_at, push to previous
                    previousArr.push({ item: item, created: new Date(0) });
                  } else if (isSameLocalDay(created, now)) {
                    todayArr.push({ item: item, created: created });
                  } else {
                    previousArr.push({ item: item, created: created });
                  }
                });

                // sort by newest first
                todayArr.sort(function(a, b) { return b.created - a.created; });
                previousArr.sort(function(a, b) { return b.created - a.created; });

                function renderList(arr) {
                  var html = '';
                  arr.forEach(function(entry) {
                    var it = entry.item;
                    var avatar = it.sender?.profile?.avatar_img ? "{{ asset('storage/profiles/') }}/" + it.sender?.profile?.avatar_img : '/images/default-user-avatar.png';
                    var title = it.title || 'Notification';
                    var message = it.message || it.title || 'Notification';
                    var timeStr = relativeTimeFrom(entry.created);
                    var payload = encodeURIComponent(JSON.stringify(it));
                    html += ''
                        + '<div class="rounded-box hover:bg-base-200 flex cursor-pointer gap-3 px-2 py-1.5 transition-all" '
                        +     'onclick="clickNotification(JSON.parse(decodeURIComponent(\'' + payload + '\')))"'
                        +   '>'
                        +   '<img class="bg-base-200 mask mask-squircle size-10 p-0.5" alt="" src="' + avatar + '" />'
                        +   '<div class="grow">'
                        +     '<p class="text-sm font-medium leading-tight">' + title + '</p>'
                        +     '<p class="text-base-content/70 text-xs leading-tight mt-1">' + message + '</p>'
                        +     '<p class="text-base-content/60 text-end text-xs leading-tight">' + timeStr + '</p>'
                        +   '</div>'
                        + '</div>';
                  });
                  return html || '<div class="px-2 py-2 text-sm text-base-content/60">No notifications</div>';
                }

                // Inject into existing containers
                $('#notification_today_container').html(renderList(todayArr));
                $('#notification_previous_container').html(renderList(previousArr));

                $('#mark_read_btn').removeAttr('disabled');
                $('#view_all_btn').removeAttr('disabled');
              } else {
                $('#notification_dot').addClass('hidden');
                $('#notification_today_container').html('<div class="px-2 py-2 text-sm text-base-content/60">No notifications</div>');
                $('#notification_previous_container').html('<div class="px-2 py-2 text-sm text-base-content/60">No notifications</div>');

                $('#mark_read_btn').attr('disabled', true);
                $('#view_all_btn').attr('disabled', true);
              }
            }
          },
          error: function() {
            console.warn('Failed to load notifications');
          }
        });
      }

      function isSameLocalDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
      }

      function relativeTimeFrom(date) {
        var now = new Date();
        var diff = Math.floor((now - date) / 1000); // seconds
        if (diff < 10) return 'just now';
        if (diff < 60) return diff + ' sec' + (diff === 1 ? '' : 's') + ' ago';
        var mins = Math.floor(diff / 60);
        if (mins < 60) return mins + ' min' + (mins === 1 ? '' : 's') + ' ago';
        var hours = Math.floor(mins / 60);
        if (hours < 24) return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
        var days = Math.floor(hours / 24);
        return days + ' day' + (days === 1 ? '' : 's') + ' ago';
      }

      // Close the notification dropdown by removing DaisyUI's open class and blurring focus
      function closeDropdown(el) {
        try {
          var $d = $(el).closest('.dropdown');
          if ($d.length) {
            $d.removeClass('dropdown-open');
            // blur any focused element inside the dropdown to ensure it collapses
            $d.find('[tabindex="0"]').blur();
          }
        } catch (e) {
          console.warn('closeDropdown error', e);
        }
      }

      function handleMarkRead() {
        $.ajax({
          url: '{{ route("mark-notification-read-user") }}',
          method: 'GET',
          dataType: 'json',
          success: function(data) {
            if (data.status) {
              $('#notification_dot').addClass('hidden');
              $('#notification_today_container').html('<div class="px-2 py-2 text-sm text-base-content/60">No notifications</div>');
              $('#notification_previous_container').html('<div class="px-2 py-2 text-sm text-base-content/60">No notifications</div>');
              $('#mark_read_btn').attr('disabled', true);
              $('#view_all_btn').attr('disabled', true);
            }
          },
          error: function() {
            console.warn('Failed to mark notifications as read');
          }
        });
      }

      function viewAllNotifications() {
        const notifUrl = '{{ route("notifications") }}';
        const notifPath = new URL(notifUrl, window.location.origin).pathname;
        if (
          window.location.pathname === notifPath ||
          window.location.href === notifUrl ||
          window.location.href.startsWith(notifUrl)
        ) {
          return;
        }
        window.location.href = notifUrl;
      }

      function clickNotification(notification) {
        console.log('clickNotification', notification);
        window.location.href = `/notification/open/${notification.id}`;
      }
    </script>
    @yield('page-js')
  </body>
</html>
