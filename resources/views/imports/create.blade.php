<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>File Import</title>
</head>
<body>

<h1>Import CSV</h1>

@if ($errors->any())
    <div>
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form
    action="{{ route('imports.store') }}"
    method="POST"
    enctype="multipart/form-data"
>
    @csrf

    <div>
        <label for="file">Upload file:</label>

        <input
            type="file"
            name="file"
            accept=".xlsx"
            required
        >
    </div>

    <br>

    <button type="submit">
        Upload File
    </button>
</form>

</body>
</html>