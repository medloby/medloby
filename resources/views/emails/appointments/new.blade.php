<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Yeni Randevu - Medloby</title>
</head>

<body style="margin: 0; padding: 0; background: #f4f6f8; font-family: Arial, Helvetica, sans-serif;">

    <div style="max-width: 650px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="padding: 24px; background: #111827; color: #ffffff;">
            <h1 style="margin: 0; font-size: 24px;">
                Yeni Randevu Geldi
            </h1>

            <p style="margin: 8px 0 0; color: #d1d5db;">
                Medloby üzerinden yeni bir randevu oluşturuldu.
            </p>
        </div>

        <div style="padding: 28px;">

            <h2 style="margin-top: 0; color: #111827;">
                Randevu Bilgileri
            </h2>

            <table style="width: 100%; border-collapse: collapse;">

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Hasta
                    </td>

                    <td style="padding: 10px 0; font-weight: bold; color: #111827;">
                        {{ $appointment->patient_name }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Telefon
                    </td>

                    <td style="padding: 10px 0; color: #111827;">
                        {{ $appointment->patient_phone }}
                    </td>
                </tr>

                @if ($appointment->patient_email)
                    <tr>
                        <td style="padding: 10px 0; color: #6b7280;">
                            E-posta
                        </td>

                        <td style="padding: 10px 0; color: #111827;">
                            {{ $appointment->patient_email }}
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Tedavi
                    </td>

                    <td style="padding: 10px 0; font-weight: bold; color: #111827;">
                        {{ $appointment->treatment?->name ?? '-' }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Doktor
                    </td>

                    <td style="padding: 10px 0; color: #111827;">
                        {{ $appointment->doctor?->person?->first_name ?? '' }}
                        {{ $appointment->doctor?->person?->last_name ?? '' }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Şube
                    </td>

                    <td style="padding: 10px 0; color: #111827;">
                        {{ $appointment->branch?->name ?? '-' }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Tarih
                    </td>

                    <td style="padding: 10px 0; font-weight: bold; color: #111827;">
                        {{ $appointment->starts_at?->format('d.m.Y') }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 10px 0; color: #6b7280;">
                        Saat
                    </td>

                    <td style="padding: 10px 0; font-weight: bold; color: #111827;">
                        {{ $appointment->starts_at?->format('H:i') }}
                        -
                        {{ $appointment->ends_at?->format('H:i') }}
                    </td>
                </tr>

                @if ($appointment->notes)
                    <tr>
                        <td style="padding: 10px 0; vertical-align: top; color: #6b7280;">
                            Not
                        </td>

                        <td style="padding: 10px 0; color: #111827;">
                            {{ $appointment->notes }}
                        </td>
                    </tr>
                @endif

            </table>

            <div style="margin-top: 28px; padding: 16px; background: #f3f4f6; border-radius: 8px;">
                <p style="margin: 0; color: #4b5563; font-size: 14px;">
                    Bu e-posta Medloby tarafından otomatik olarak gönderilmiştir.
                </p>
            </div>

        </div>

        <div style="padding: 18px 28px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;">
            © {{ date('Y') }} Medloby
        </div>

    </div>

</body>
</html>