{{-- Stat cards. $items = [['value'=>..., 'label'=>..., 'color'=>'#1F6FB2', 'small'=>false], ...] --}}
<table class="cards" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        @foreach ($items as $it)
            <td class="card" style="border-top: 4px solid {{ $it['color'] }}; width: {{ round(100 / count($items), 2) }}%;">
                <div class="card-value" style="color: {{ $it['color'] }}; font-size: {{ !empty($it['small']) ? '15pt' : '22pt' }};">{{ $it['value'] }}</div>
                <div class="card-label">{{ $it['label'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
