<div class="btn-group" data-toggle="buttons">
    @foreach($options as $option => $label)
    <label class="btn btn-default btn-sm {{ \Request::get('errs', 'all') == $option ? 'active' : '' }}">
        <input type="radio" class="pro-err" value="{{ $option }}">{{$label}}
    </label>
    @endforeach
</div>