<!DOCTYPE html>
<html>

<head>
    @if($title)
    <title>{{$title}}</title>
    @else
    <title>Files Report</title>
    @endif
</head>

<body>
    @if($heading)
    <h1>{{$heading}}</h1>
    @else
    <h1>Files Report</h1>
    @endif
    @if($body)
    <p>{{$body}}</p>
    @else
    <p>Please find the attached files.</p>
    @endif
</body>

</html>
