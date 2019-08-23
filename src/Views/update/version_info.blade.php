@php($updateVersionInfo = \Froiden\Envato\Functions\EnvatoUpdate::updateVersionInfo())
@php($envatoUpdateCompanySetting = \Froiden\Envato\Functions\EnvatoUpdate::companySetting())
<div class="table-responsive">

    <table class="table table-bordered">
        <thead>
        <th>@lang('modules.update.systemDetails')</th>
        </thead>
        <tbody>
        <tr>
            <td>App Version <span
                        class="pull-right">{{ $updateVersionInfo['appVersion'] }}</span></td>
        </tr>
        <tr>
            <td>Laravel Version <span
                        class="pull-right">{{ $updateVersionInfo['laravelVersion'] }}</span></td>
        </tr>
        <tr>
            <td>PHP Version <span class="pull-right">{{ phpversion() }}</span></td>
        </tr>
        @if(!is_null($envatoUpdateCompanySetting->purchase_code))
            <tr>
                <td>Envato Purchase code <span
                            class="pull-right">{{$envatoUpdateCompanySetting->purchase_code}}</span>
                </td>
            </tr>
        @endif
        </tbody>
    </table>
</div>