<div class="space-y-4 text-sm text-base-content/70">
  <h4 class="text-xl font-semibold mb-4">Time Slots</h4>
  <p>Generate and manage available time slots for services. Time slots define when appointments can be scheduled.</p>

  <p><strong>Features:</strong></p>
  <ul class="list-disc list-inside space-y-1 ml-4">
    <li>Generate time slots automatically for date ranges</li>
    <li>Manually create individual time slots</li>
    <li>Edit or delete existing time slots</li>
    <li>View holidays that affect time slot generation</li>
    <li>Handle restricted booking dates</li>
  </ul>

  <p class="mt-3 mb-2"><strong>How to Generate Time Slots:</strong></p>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>Select a service from the dropdown</li>
    <li>Choose a date to view existing time slots</li>
    <li>Click "Generate" button</li>
    <li>In the modal, select a start date and end date (up to 12 months range)</li>
    <li>Review holidays in the selected period</li>
    <li>Click "Confirm" to generate time slots</li>
  </ol>

  <p class="mt-3 mb-2"><strong>Important Notes:</strong></p>
  <ul class="list-disc list-inside space-y-1 ml-4">
    <li>Time slots are not generated for dates with holidays that have "Restrict Bookings" enabled</li>
    <li>Time slots are not generated for dates that already have existing time slots</li>
    <li>The date range cannot exceed 12 months (365 days)</li>
    <li>Start date cannot be in the past or have existing time slots</li>
    <li>End date must be greater than or equal to start date (allowing single-day generation)</li>
  </ul>

  <p class="mt-3 mb-2"><strong>Manual Time Slot Creation:</strong></p>
  <p>Click "New" to manually create a time slot with specific time, staff assignment, and capacity.</p>

  <div class="mt-8 pt-6 border-t border-base-content/10 video-container">
    <video controls class="w-full rounded-lg shadow-lg help-video" preload="metadata">
      <source src="{{ asset('videos/help/time-slots.mp4') }}" type="video/mp4">
      Your browser does not support the video tag.
    </video>
  </div>
</div>
