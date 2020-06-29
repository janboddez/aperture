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

        <div class="content">
            <form action="{{ route('settings_save') }}" method="post" style="margin-top: 1.5rem;">
                {{ csrf_field() }}

                <h2 class="subtitle">Demo Mode</h2>
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="demo_mode_enabled" {{ Auth::user()->demo_mode_enabled ? 'checked="checked"' : '' }}>
                        Enable demo mode
                    </label>
                    <p class="help">Hides certain channels from the UI and Microsub clients. Choose which channels are hidden in the channel settings.</p>
                </div>

                <h2 class="subtitle" style="margin-top: 1.5rem;">OPML Endpoint</h2>
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="opml_endpoint_enabled" {{ Auth::user()->opml_endpoint_enabled ? 'checked="checked"' : '' }}>
                        Enable public OPML endpoint
                    </label>
                    <p class="help">Publicly publishes your channels and sources, using OPML.</p>
                </div>

                <h2 class="subtitle" style="margin-top: 1.5rem;">Fetch Original Content</h2>
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="fetch_original_enabled" {{ Auth::user()->fetch_original_enabled ? 'checked="checked"' : '' }}>
                        Enable "Fetch Original Content"
                    </label>
                    <p class="help">Enables fetching full articles, e.g., for summarry-only feeds.</p>
                </div>

                <div class="control">
                    <button class="button is-primary">Save</button>
                </div>
            </form>

            <h2 class="subtitle">Micropub Config</h2>
            <pre style="margin-bottom: 0.875rem;" id="micropub-config">{{ json_encode(json_decode(Auth::user()->micropub_config), JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES) }}</pre>
            <div class="control"><button class="button is-primary" id="reload-micropub-config">Reload</button></div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<style>
.content h2,
.content h3 {
    font-size: 1.25rem;
    font-weight: 400;
    margin-bottom: 1.5rem;
}
</style>

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
