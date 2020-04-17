@extends('layouts/main')

@section('content')
<section class="section">
<div class="container dashboard">

  <div class="notification is-info content">
    <p>Thanks for trying out this beta version of Aperture! Currently only the last 7 days of posts are saved. You should feel free to bookmark or favorite things so that they get permanently saved to your own site!</p>
    <p>Feedback is always appreciated! If you have any problems, you can <a href="https://github.com/aaronpk/Aperture/issues">file an issue</a> on GitHub, or get in touch with aaronpk via the <a href="https://indieweb.org/discuss">IndieWeb chat</a>!</p>
  </div>

  <div class="buttons is-right">
    <a href="#" id="import-opml" class="button">Import from OPML</a>
    <a href="#" id="new-channel" class="button is-primary">New Channel</a>
  </div>

  <h1 class="title">Channels</h1>

  <div class="channels">
  <?php $numChannels = count($channels); ?>
  @foreach($channels as $i=>$channel)
    <div class="channel" data-uid="{{ $channel->uid }}">
      @if($channel->uid != 'notifications')
        <div class="sort">
          <a href="#" data-dir="up" {!! (! $channels->contains('uid', 'notifications') && $i > 0) || ($channels->contains('uid', 'notifications') && $i > 1) ? '' : 'class="disabled"' !!}><i class="fas fa-caret-up"></i></a>
          <a href="#" data-dir="down" {!! $i < $numChannels-1 ? '' : 'class="disabled"' !!}><i class="fas fa-caret-down"></i></a>
        </div>
      @endif

      <h2><a href="{{ route('channel', $channel) }}">{{ $channel->name }}</a></h2>

      <!-- sparkline -->

      <div class="channel-stats">
        @if( ($count=$channel->sources()->count()) > 0 )
          <span>{{ $count }} Sources</span>
        @endif
        @if( $channel->last_entry_at )
          <span>Last item {n} minutes ago</span>
        @endif
      </div>
    </div>
  @endforeach
  </div>

  @if(count($archived))
    <div style="margin-top: 2em;">
      <h2 class="subtitle">Archived Channels</h2>
      <ul>
      @foreach($archived as $channel)
        <li><a href="{{ route('channel', $channel) }}">{{ $channel->name }}</a></li>
      @endforeach
      </ul>
    </div>
  @endif

  <hr>

  <div class="helpsection">
    <h3 class="subtitle">Get Started</h3>

    <p>To use Aperture as your Microsub endpoint, add this HTML to your home page.</p>

    <pre><?= htmlspecialchars('<link rel="microsub" href="'.env('APP_URL').'/microsub/'.Auth::user()->id.'">') ?></pre>

    <p>Then choose a <a href="https://indieweb.org/Microsub#Clients">reader</a> and log in, and the reader will find your subscriptions and data in Aperture.</p>
  </div>

</div>
</section>

<div class="modal" id="import-opml-modal">
    <div class="modal-background"></div>
    <div class="modal-card">
      <form action="{{ route('import_opml') }}" method="POST" enctype="multipart/form-data">
        {{ csrf_field() }}
        <header class="modal-card-head">
          <p class="modal-card-title">Import from OPML</p>
          <button class="delete" aria-label="close"></button>
        </header>

        <section class="modal-card-body">
          <div class="file has-name is-fullwidth">
            <label class="file-label">
              <input class="file-input" type="file" name="opml" required="required" accept="text/xml">
              <span class="file-cta">
                <span class="file-icon">
                  <i class="fas fa-upload"></i>
                </span>
                <span class="file-label">
                  Choose a file…
                </span>
              </span>
              <span class="file-name">
                No file uploaded
              </span>
            </label>
          </div>
        </section>

        <footer class="modal-card-foot">
          <button class="button is-primary" type="submit">Import</button>
        </footer>
      </form>
    </div>
</div>

<div class="modal" id="new-channel-modal">
    <div class="modal-background"></div>
    <div class="modal-card">
      <form action="{{ route('create_channel') }}" method="POST">
        {{ csrf_field() }}
        <header class="modal-card-head">
          <p class="modal-card-title">Create a Channel</p>
          <button class="delete" aria-label="close"></button>
        </header>

        <section class="modal-card-body">
          <input class="input" type="text" placeholder="Name" name="name" required="required">
        </section>

        <footer class="modal-card-foot">
          <button class="button is-primary" type="submit">Create</button>
        </footer>
      </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function(){
  $('#new-channel').click(function(e){
    $('#new-channel-modal').addClass('is-active');
    $("#new-channel-modal input[name='name']").focus();
    e.preventDefault();
  });

  $('#import-opml').click(function(e){
    $('#import-opml-modal').addClass('is-active');
    $("#import-opml-modal input[name='opml']").focus();
    e.preventDefault();
  });

  $('#import-opml-modal button[type="submit"]').click(function(e){
    if ($('#import-opml-modal input[name="opml"]').get(0).files.length !== 0) {
      $(this).addClass('is-loading');
    }
  });

  $('.channel .sort a').click(function(e){
    e.preventDefault();
    if($(this).hasClass("disabled")) { return; }

    var newOrder;

    if($(this).data("dir") == "up") {
      var thisChannel = $($(this).parents(".channel")[0]).data("uid");
      var prevChannel = $(".channel[data-uid="+thisChannel+"]").prev().data("uid");
      newOrder = [thisChannel, prevChannel];
    } else {
      var thisChannel = $($(this).parents(".channel")[0]).data("uid");
      var nextChannel = $(".channel[data-uid="+thisChannel+"]").next().data("uid");
      newOrder = [nextChannel, thisChannel];
    }

    $.post("/channel/set_order", {
      channels: newOrder,
      _token: csrf_token()
    }, function(){
      window.location.reload();
    })
  });
});

$('#import-opml-modal input[name="opml"]').change(function(){
  if (this.files.length > 0) {
    $('#import-opml-modal .file-name').text(this.files[0].name);
  }
});
</script>
<style>
.helpsection p {
  margin: 1em 0;
}
.helpsection ul.methods {
  list-style-type: disc;
  margin-left: 1em;
}
.channels .sort {
  float: right;
}
.channels .sort a {
  font-size: 1.1em;
}
.channels .sort a.disabled {
  cursor: auto;
  color: #ccc;
}
</style>
@endsection
