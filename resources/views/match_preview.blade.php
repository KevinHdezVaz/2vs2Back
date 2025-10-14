<!DOCTYPE html>
<html>
<head>
    <title>Partido #{{ $matchId }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>Únete al Partido #{{ $matchId }}</h1>
    <p>Para ver los detalles y unirte al partido, abre este enlace en tu dispositivo móvil con la app instalada:</p>
    <p><a href="{{ $deepLink }}">{{ $deepLink }}</a></p>
    <p>Si no tienes la app instalada, descárgala aquí:</p>
    <ul>
        <li><a href="{{ $androidFallbackUrl }}">Descargar para Android</a></li>
        <li><a href="{{ $iosFallbackUrl }}">Descargar para iOS</a></li>
    </ul>
</body>
</html>