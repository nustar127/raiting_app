<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Рейтинговый список</title>
    @include('reports.pdf._style')
</head>

<body>
    <div class="header">
        <h1>Рейтинговый список</h1>
        <div>Период: <strong>{{ $data['period']['name'] ?? '—' }}</strong></div>
        <div>Сформирован: {{ $data['generated_at'] ?? '—' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Место</th>
                <th>ФИО</th>
                <th>Номер зачётки</th>
                <th>Группа</th>
                <th class="text-right">Рейтинг</th>
                <th class="text-right">Дисциплин</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] ?? [] as $item)
            <tr>
                <td>{{ $item['rank'] }}</td>
                <td>{{ $item['full_name'] }}</td>
                <td>{{ $item['record_book_number'] }}</td>
                <td>{{ $item['group_name'] }}</td>
                <td class="text-right">{{ number_format($item['cumulative_value'], 2, ',', ' ') }}</td>
                <td class="text-right">{{ $item['disciplines_count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Страница {PAGE_NUM} из {PAGE_COUNT}</div>
</body>

</html>