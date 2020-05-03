@extends('layouts/main')

@section('content')
<section class="section">
    <div class="container dashboard">
        <h1 class="title">Settings</h1>

        @if (session('settings'))
            <div class="notification is-primary">
                {{ session('settings') }}
            </div>
        @endif

        <h3 class="subtitle" style="margin-top: 1.5rem;">Demo Mode</h3>
        <form action="{{ route('settings_save') }}" method="post">
            {{ csrf_field() }}

            <div class="field">
                <label class="checkbox">
                    <input type="checkbox" name="demo_mode_enabled" {{ Auth::user()->demo_mode_enabled ? 'checked="checked"' : '' }}>
                    Enable "Demo Mode"
                </label>
                <p class="help">Hides certain channels from the UI and Microsub clients. Choose which channels are hidden in the channel settings.</p>
            </div>

            <div class="control">
                <button class="button is-primary">Save</button>
            </div>
        </form>

        <hr style="margin-top: 1.875rem;">

        <h3 class="subtitle">Micropub Config</h3>
        <pre style="margin-bottom: 0.875rem;" id="micropub-config">{{ json_encode(json_decode(Auth::user()->micropub_config), JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES) }}</pre>
        <div class="control"><button class="button is-primary" id="reload-micropub-config">Reload</button></div>
    </div>
</section>
@endsection

@section('scripts')
<script>
$(function() {
    $("#reload-micropub-config").click(function() {
        var btn = $(this);
        btn.addClass("is-loading");
        $.post("{{ route('reload_micropub_config') }}", {
            _token: csrf_token(),
        }, function(response) {
            btn.removeClass("is-loading");
            $("#micropub-config").text(response.json);
        });
    });
});
</script>
@endsection
