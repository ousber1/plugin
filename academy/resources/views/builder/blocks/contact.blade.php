<div class="bk-contact card card--pad">
    @include('builder.blocks._head', ['title' => $t('title'), 'sub' => $t('text')])
    <form method="POST" action="{{ route('contact.store') }}">
        @csrf
        <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid">
            <div class="field"><label for="bkc-name">{{ __('الاسم') }}</label><input class="input" id="bkc-name" name="name" required maxlength="120" dir="auto"></div>
            <div class="field"><label for="bkc-email">{{ __('البريد الإلكتروني') }}</label><input class="input input-ltr" id="bkc-email" type="email" name="email" required></div>
        </div>
        <div class="field"><label for="bkc-body">{{ __('رسالتك') }}</label><textarea class="textarea" id="bkc-body" name="body" required minlength="10" maxlength="5000" dir="auto"></textarea></div>
        <button class="btn btn--primary" type="submit">{{ __('إرسال') }}</button>
    </form>
</div>
