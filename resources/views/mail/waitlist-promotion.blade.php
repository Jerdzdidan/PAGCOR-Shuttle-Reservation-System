<x-mail::message>
# Your shuttle seat is confirmed

Hello {{ $employeeName }},

A seat became available and your waitlist entry was automatically promoted.

**Route:** {{ $routeName }}  
**Travel date:** {{ $travelDate }}  
**Departure time:** {{ $departureTime }}  
**Vehicle plate:** {{ $plateNumber }}  
**Assigned seat:** {{ $seatNumber }}

Please open the employee portal to review your confirmed reservation.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
