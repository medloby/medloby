<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Randevunuz Oluşturuldu</title>
</head>
<body>
    <h2>Randevunuz oluşturuldu</h2>

    <p>
        Merhaba {{ $appointment->patientProfile?->first_name }},
    </p>

    <p>
        Medloby üzerinden randevunuz başarıyla oluşturuldu.
    </p>

    <h3>Randevu Bilgileri</h3>

    <p>
        <strong>İşletme:</strong>
        {{ $appointment->business?->name }}
    </p>

    <p>
        <strong>Şube:</strong>
        {{ $appointment->branch?->name }}
    </p>

    <p>
        <strong>Doktor:</strong>
        {{ $appointment->doctor?->person?->first_name }}
        {{ $appointment->doctor?->person?->last_name }}
    </p>

    <p>
        <strong>Tedavi:</strong>
        {{ $appointment->treatment?->name }}
    </p>

    <p>
        <strong>Tarih:</strong>
        {{ $appointment->starts_at?->format('d.m.Y') }}
    </p>

    <p>
        <strong>Saat:</strong>
        {{ $appointment->starts_at?->format('H:i') }}
        -
        {{ $appointment->ends_at?->format('H:i') }}
    </p>

    <p>
        Randevunuzun durumunu Medloby hesabınız üzerinden takip edebilirsiniz.
    </p>

    <p>
        İyi günler dileriz.
    </p>

    <p>
        <strong>Medloby</strong>
    </p>
</body>
</html>