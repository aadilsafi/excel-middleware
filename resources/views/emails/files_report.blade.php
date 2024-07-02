<!DOCTYPE html>
<html>

<head>
    @if($title)
    {{$title}}
    @else
    <title>Files Report</title>
    @endif
</head>

<body>
    @if($heading)
    {{$heading}}
    @else
    <h1>Files Report</h1>
    @endif
    @if($body)
    {{$body}}
    @else
    <p>Please find the attached files.</p>
    @endif
</body>

</html>
