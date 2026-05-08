@extends('layouts.main')
@section('title', 'Appointments')

@section('page-css')
<style>
  .fc .fc-button {
    padding: 0.25rem 0.6rem;
    font-size: 0.875rem;
  }
  .fc .fc-button .fc-icon {
    font-size: 1rem;
    line-height: 0.8;
  }
  .fc .fc-scrollgrid-section-sticky > * {
    background-color: var(--bc-base-100);
  }
  .fc-h-event {
    background: unset !important;
    border: unset !important;
  }
  .fc-h-event .fc-event-main {
    color: unset !important;
  }
  #weekly_view_mode {
    margin-bottom: 8px;
  }
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <div class="inline-flex items-center gap-3">
    <h3 class="text-lg font-medium">Appointments</h3>
    <a class="btn btn-primary btn-sm max-sm:btn-square w-36" href="{{ route('appointments') }}">
      <span class="iconify lucide--calendar-days size-4"></span>
      <span class="hidden sm:inline">View List</span>
    </a>
  </div>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li>Appointments</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  <div class="card bg-base-100 shadow mt-3">
    <div class="card-body p-4">
      <div class="flex flex-row items-end gap-3 sm:flex-row sm:items-end sm:gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium" for="weekly_view_mode">Weekly View</label>
          <select id="weekly_view_mode" class="select select-bordered select-sm w-auto min-w-[220px]">
            <option value="standard" selected>Standard Week (Mon-Sun)</option>
            <option value="boarding">Boarding Week (Thu-Mon)</option>
            <option value="custom">Custom</option>
          </select>
        </div>

        <div id="custom_weekly_range" class="hidden flex-wrap items-center gap-2 rounded-box bg-base-200/40 px-3 py-2">
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium whitespace-nowrap" for="custom_start_day">Start</label>
            <select id="custom_start_day" class="select select-bordered select-sm w-auto min-w-[140px]">
              <option value="1">Monday</option>
              <option value="2">Tuesday</option>
              <option value="3">Wednesday</option>
              <option value="4">Thursday</option>
              <option value="5">Friday</option>
              <option value="6">Saturday</option>
              <option value="0">Sunday</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium whitespace-nowrap" for="custom_end_day">End</label>
            <select id="custom_end_day" class="select select-bordered select-sm w-auto min-w-[140px]">
              <option value="1">Monday</option>
              <option value="2">Tuesday</option>
              <option value="3">Wednesday</option>
              <option value="4">Thursday</option>
              <option value="5">Friday</option>
              <option value="6">Saturday</option>
              <option value="0">Sunday</option>
            </select>
          </div>
          <button type="button" id="apply_custom_week" class="btn btn-primary btn-sm shrink-0">Apply</button>
        </div>
      </div>

      <div id="calendar"></div>
    </div>
  </div>
</div>
@endsection

@section('page-js')
  <script src="{{ asset('src/libs/fullcalendar-6.1.19/dist/index.global.min.js') }}"></script>
  <script>
    const appointments = @js($appointments);
    const weekModeDefaults = {
      standard: { startDay: 1, endDay: 0, durationDays: 7, label: 'Week' },
      boarding: { startDay: 4, endDay: 1, durationDays: 5, label: 'Boarding Week' },
    };

    document.addEventListener('DOMContentLoaded', function() {
      const serviceColors = {
        'Groom': '#3b82f6',
        'Grooming': '#3b82f6',
        'Daycare': '#10b981',
        'Boarding': '#f59e42',
        'Training': '#ef4444',
        'Chauffeur': '#8b5cf6',
        'Package': '#f472b6',
        'GroupClass': '#06b6d4', // Add color for Group Class
      };

      function normalizeServiceName(name) {
        if (!name) return 'Other';
        const value = String(name).trim().toLowerCase();
        if (value.includes('board')) return 'Boarding';
        if (value.includes('daycare') || value.includes('day care')) return 'Daycare';
        if (value.includes('groom')) return 'Grooming';
        if (value.includes('chauffeur') || value.includes('transport')) return 'Chauffeur';
        if (value.includes('train')) return 'Training';
        if (value.includes('package')) return 'Package';
        if (value.includes('group class') || value.includes('groupclass')) return 'GroupClass'; // Add normalization for Group Class
        return 'Other';
      }

      function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      }

      function getDatesInRange(start, end) {
        const dates = [];
        const current = new Date(start);

        while (current < end) {
          dates.push(formatDate(current));
          current.setDate(current.getDate() + 1);
        }

        return dates;
      }

      function getAppointmentsByDate(date) {
        return appointments.filter(item => item.date === date);
      }

      function getSummaryByDate(items) {
        const summary = {
          Boarding: 0,
          Daycare: 0,
          Grooming: 0,
          Chauffeur: 0,
          Package: 0,
          GroupClass: 0 // Add GroupClass to summary
        };
        items.forEach(item => {
          const service = normalizeServiceName(item.service_name);
          if (summary.hasOwnProperty(service)) {
            summary[service]++;
          }
        });
        return summary;
      }

      function buildSummaryEventHtml(summary) {
        // Build a map of normalized service name to service ID from the appointments array
        const serviceIdMap = {};
        appointments.forEach(item => {
          const key = normalizeServiceName(item.service_name);
          if (item.service_id && !serviceIdMap[key]) {
            serviceIdMap[key] = item.service_id;
          }
        });
        const services = [
          { key: 'Boarding', label: 'Boarding', color: serviceColors.Boarding },
          { key: 'Daycare', label: 'Daycare', color: serviceColors.Daycare },
          { key: 'Grooming', label: 'Grooming', color: serviceColors.Grooming },
          { key: 'Chauffeur', label: 'Chauffeur', color: serviceColors.Chauffeur },
          { key: 'Package', label: 'Package', color: serviceColors.Package },
          { key: 'GroupClass', label: 'Group Class', color: serviceColors.GroupClass },
        ];
        const rows = services
          .filter(s => summary[s.key] && summary[s.key] > 0)
          .map(s => {
            const serviceId = serviceIdMap[s.key] || '';
            const url = serviceId ? `/dashboard/service/${serviceId}` : '#';
            return `<div style="display:flex;align-items:center;gap:8px;white-space:nowrap;cursor:pointer;" onclick="if('${serviceId}')window.location.href='${url}'">
              <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:${s.color};"></span>
              <span>${s.label} (${summary[s.key]})</span>
            </div>`;
          })
          .join('');
        return `
          <div style="display:flex;flex-direction:column;gap:4px;padding:2px 6px;">
            ${rows || '<div style="height:18px;"></div>'}
          </div>
        `;
      }

      const events = appointments.map(appointment => ({
        id: appointment.id,
        title: appointment.pet_name,
        start: `${appointment.date}T${appointment.start_time}`,
        end: `${appointment.date}T${appointment.end_time}`,
        url: `/appointment/edit/${appointment.id}`, // Link to appointment details
        color: serviceColors[normalizeServiceName(appointment.service_name)] || '#6366f1', // Use normalized service name for color
      }));

      function getWeekDurationDays(startDay, endDay) {
        return ((endDay - startDay + 7) % 7) + 1;
      }

      function getAnchorDateForWeek(baseDate, startDay) {
        const anchor = new Date(baseDate);
        anchor.setHours(0, 0, 0, 0);
        const diff = (anchor.getDay() - startDay + 7) % 7;
        anchor.setDate(anchor.getDate() - diff);
        return anchor;
      }

      function getHiddenDays(startDay, endDay) {
        const visibleDays = [];
        let currentDay = startDay;

        while (true) {
          visibleDays.push(currentDay);
          if (currentDay === endDay) {
            break;
          }
          currentDay = (currentDay + 1) % 7;
        }

        return [0, 1, 2, 3, 4, 5, 6].filter(day => !visibleDays.includes(day));
      }

      const standardAnchorDate = getAnchorDateForWeek(new Date(), weekModeDefaults.standard.startDay);

      const weeklyViewModeEl = document.getElementById('weekly_view_mode');
      const customWeeklyRangeEl = document.getElementById('custom_weekly_range');
      const customStartDayEl = document.getElementById('custom_start_day');
      const customEndDayEl = document.getElementById('custom_end_day');
      const applyCustomWeekEl = document.getElementById('apply_custom_week');

      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialDate: standardAnchorDate,
        initialView: 'timeGridWeek',
        firstDay: weekModeDefaults.standard.startDay,
        views: {
          timeGridWeek: {
            type: 'timeGrid',
            duration: { days: weekModeDefaults.standard.durationDays },
            dateAlignment: 'day',
            dateIncrement: { days: weekModeDefaults.standard.durationDays },
            buttonText: weekModeDefaults.standard.label,
          }
        },
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: events,
        slotMinTime: "08:00:00",
        slotMaxTime: "19:00:00",
        height: "auto",

        eventOrder: function(a, b) {
          // In Month view: Summary (top), All-day (middle), Time slot (bottom)
          const viewType = calendar.view?.type;
          const aSummary = a.extendedProps?.isSummaryRow ? 1 : 0;
          const bSummary = b.extendedProps?.isSummaryRow ? 1 : 0;
          const aAllDay = a.extendedProps?.isAllDayRow ? 1 : 0;
          const bAllDay = b.extendedProps?.isAllDayRow ? 1 : 0;

          if (viewType === 'dayGridMonth') {
            // Summary first
            if (aSummary !== bSummary) return bSummary - aSummary;
            // All-day second
            if (aAllDay !== bAllDay) return bAllDay - aAllDay;
            // Time slot events last
            return 0;
          } else {
            // Default: Summary, then All-day, then time slot (old logic)
            if (aSummary !== bSummary) return bSummary - aSummary;
            if (aAllDay !== bAllDay) return aAllDay - bAllDay;
            return 0;
          }
        },

        eventContent: function(arg) {
          if (arg.event.extendedProps?.isSummaryRow) {
            return {
              html: buildSummaryEventHtml(arg.event.extendedProps.summary)
            };
          }
          // All-day feature removed: do not render all-day events
          // For normal events (time slots)
          const start = arg.event.start;
          const end = arg.event.end;
          const pad = n => String(n).padStart(2, '0');
          // Month view: dot, time, bold pet name (like image)
          if (calendar.view?.type === 'dayGridMonth') {
            const formatTime = d => {
              let h = d.getHours();
              let m = d.getMinutes();
              let ampm = h < 12 ? 'a' : 'p';
              h = h % 12;
              if (h === 0) h = 12;
              return `${h}${m ? ':' + pad(m) : ''}${ampm}`;
            };
            let timeStr = '';
            if (start) {
              timeStr = formatTime(start);
            }
            return {
              html: `<span style="display:inline-flex;align-items:center;gap:2px;font-size:14px;padding-left:7px">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${arg.event.backgroundColor || arg.event.color || '#6366f1'};margin-right:4px;"></span>
                <span style="color:#444;">${timeStr}</span>
                <span style="font-weight:600;margin-left:2px;color:#222;">${arg.event.title}</span>
              </span>`
            };
          }
          // Week/Day view: keep previous style
          const formatTime = d => `${pad(d.getHours())}:${pad(d.getMinutes()).padStart(2, '0')}`;
          let timeStr = '';
          if (start && end) {
            timeStr = `${formatTime(start)} - ${formatTime(end)}`;
          } else if (start) {
            timeStr = formatTime(start);
          }
          return {
            html: `<div style="color:#000; padding:2px 6px;">
              <div>${timeStr}</div>
              <div>${arg.event.title}</div>
            </div>`
          };
        },

        eventDidMount: function(info) {
          // Only add tooltip for normal events (not summary or all-day)
          if (!info.event.extendedProps?.isSummaryRow && !info.event.extendedProps?.isAllDayRow) {
            info.el.setAttribute('title',
              `Pet: ${info.event.title}
Customer: ${appointments.find(a => a.id == info.event.id)?.customer_name || ''}
Service: ${appointments.find(a => a.id == info.event.id)?.service_name || ''}
${appointments.find(a => a.id == info.event.id)?.class_name ? `Class: ${appointments.find(a => a.id == info.event.id)?.class_name}` : ''}`
            );
          }
        },
        datesSet: function(info) {
          const visibleDates = getDatesInRange(info.start, info.end);

          if (info.view.type === "dayGridMonth") {
            $('.fc-timegrid-axis-cushion').text(''); // Remove "all-day" text from time grid
          }

          if (info.view.type === "timeGridWeek") {
            $('.fc-timegrid-axis-cushion').text(''); // Remove "all-day" text from time grid
          }

          if (info.view.type === "timeGridDay") {
            $('.fc-timegrid-axis-cushion').text(''); // Remove "all-day" text from time grid
          }

          calendar.getEvents().forEach(event => {
            if (event.extendedProps?.isSummaryRow || event.extendedProps?.isAllDayRow) {
              event.remove();
            }
          });

          visibleDates.forEach(date => {
            const dayAppointments = getAppointmentsByDate(date);
            const summary = getSummaryByDate(dayAppointments);

            // Only show summary if there is at least one service with pets
            const hasSummary = ['Boarding','Daycare','Grooming','Chauffeur','Package','GroupClass'].some(key => summary[key] && summary[key] > 0);
            if (hasSummary) {
              calendar.addEvent({
                id: `summary-${date}`,
                start: date,
                allDay: true,
                display: 'block',
                title: 'Summary',
                extendedProps: {
                  isSummaryRow: true,
                  summary: summary,
                }
              });
            }
          });
        }
      });

      function applyWeeklyMode(mode) {
        let weekConfig;

        if (mode === 'custom') {
          const startDay = Number(customStartDayEl.value);
          const endDay = Number(customEndDayEl.value);
          const durationDays = getWeekDurationDays(startDay, endDay);
          weekConfig = {
            startDay,
            endDay,
            durationDays,
            label: `${durationDays} Days`,
          };
        } else {
          weekConfig = weekModeDefaults[mode] || weekModeDefaults.standard;
        }

        const currentDate = calendar.getDate();
        const anchorDate = getAnchorDateForWeek(currentDate, weekConfig.startDay);

        calendar.setOption('firstDay', weekConfig.startDay);
        calendar.setOption('hiddenDays', getHiddenDays(weekConfig.startDay, weekConfig.endDay));
        calendar.setOption('views', {
          timeGridWeek: {
            type: 'timeGrid',
            duration: { days: weekConfig.durationDays },
            dateAlignment: 'day',
            dateIncrement: { days: weekConfig.durationDays },
            buttonText: weekConfig.label,
          }
        });

        if (calendar.view.type === 'timeGridWeek') {
          calendar.changeView('timeGridWeek', anchorDate);
        }
      }

      weeklyViewModeEl.addEventListener('change', function() {
        const selectedMode = weeklyViewModeEl.value;
        const isCustom = selectedMode === 'custom';
        customWeeklyRangeEl.classList.toggle('hidden', !isCustom);
        customWeeklyRangeEl.classList.toggle('flex', isCustom);

        if (!isCustom) {
          applyWeeklyMode(selectedMode);
        }
      });

      applyCustomWeekEl.addEventListener('click', function() {
        applyWeeklyMode('custom');
      });

      calendar.render();
    });
  </script>
@endsection