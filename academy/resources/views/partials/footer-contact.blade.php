@php use App\Support\Settings; @endphp
<ul class="footer-contact">
    @if (Settings::get('contact_email'))<li><x-icon name="mail" size="16"/> <a href="mailto:{{ Settings::get('contact_email') }}" class="ltr">{{ Settings::get('contact_email') }}</a></li>@endif
    @if (Settings::get('contact_phone'))<li><x-icon name="phone" size="16"/> <a class="ltr" href="tel:{{ preg_replace('/\s+/', '', Settings::get('contact_phone')) }}">{{ Settings::get('contact_phone') }}</a></li>@endif
    @if (Settings::get('whatsapp_number') && Settings::get('whatsapp_number') !== '212600000000')<li><x-icon name="whatsapp" size="16"/> <a class="ltr" href="{{ Settings::whatsappLink(__('السلام عليكم')) }}" target="_blank" rel="noopener">+{{ Settings::get('whatsapp_number') }}</a></li>@endif
    @if (Settings::tr('contact_address'))<li><x-icon name="map-pin" size="16"/> <span>{{ Settings::tr('contact_address') }}</span></li>@endif
</ul>
