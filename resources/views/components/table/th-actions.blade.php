@props(['show' => true, 'label' => 'Action'])
@if($show)
<th {{ $attributes->class(['text-center']) }}>{{ $label }}</th>
@endif
