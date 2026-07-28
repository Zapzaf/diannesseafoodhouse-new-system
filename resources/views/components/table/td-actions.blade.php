@props(['show' => true])
@if($show)
<td {{ $attributes->class(['text-center']) }}>{{ $slot }}</td>
@endif
