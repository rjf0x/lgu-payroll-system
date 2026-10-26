@if (session('status'))
    <div role="status" class="feedback feedback-success"><x-ui.icon name="check" /><p>{{ session('status') }}</p></div>
@endif
@if ($errors->any())
    <div role="alert" tabindex="-1" aria-labelledby="validation-heading" class="feedback feedback-error" data-error-summary>
        <div>
            <h2 id="validation-heading">Please check the following:</h2>
            <ul>
                @foreach ($errors->getMessages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><a href="#main-content" data-error-field="{{ $field }}">{{ $message }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
@endif
