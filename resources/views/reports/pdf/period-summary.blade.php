<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сводка по периоду</title>
    @include('reports.pdf._style')
</head>
<body>
    <div class="header">
        <h1>Сводка по периоду</h1>
        <div>Период: <strong>{{ $data['period']['name'] ?? '—' }}</strong></div>
    </div>

    <table class="meta">
        <tr><td>Студентов</td><td>{{ $data['totals']['students_count'] ?? 0 }}</td></tr>
        <tr><td>Средний рейтинг</td><td>{{ number_format($data['totals']['avg_rating'] ?? 0, 2, ',', ' ') }}</td></tr>
        <tr><td>Максимум</td><td>{{ number_format($data['totals']['max_rating'] ?? 0, 2, ',', ' ') }}</td></tr>
        <tr><td>Минимум</td><td>{{ number_format($data['totals']['min_rating'] ?? 0, 2, ',', ' ') }}</td></tr>
    </table>

    <h2>Распределение</h2>
    <table>
        <thead><tr><th>Диапазон</th><th class="text-right">Студентов</th></tr></thead>
        <tbody>
            @foreach($data['distribution'] ?? [] as $bucket => $count)
                <tr><td>{{ $bucket }}</td><td class="text-right">{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>По группам</h2>
    <table>
        <thead><tr><th>Группа</th><th class="text-right">Студентов</th><th class="text-right">Средний</th></tr></thead>
        <tbody>
            @foreach($data['groups'] ?? [] as $g)
                <tr>
                    <td>{{ $g['group_name'] }}</td>
                    <td class="text-right">{{ $g['students_count'] }}</td>
                    <td class="text-right">{{ number_format($g['avg_rating'], 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>По дисциплинам</h2>
    <table>
        <thead><tr><th>Дисциплина</th><th>Шифр</th><th class="text-right">Студентов</th><th class="text-right">Средний</th></tr></thead>
        <tbody>
            @foreach($data['disciplines'] ?? [] as $d)
                <tr>
                    <td>{{ $d['discipline_name'] }}</td>
                    <td>{{ $d['discipline_code'] }}</td>
                    <td class="text-right">{{ $d['students_count'] }}</td>
                    <td class="text-right">{{ number_format($d['avg_rating'], 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Страница {PAGE_NUM} из {PAGE_COUNT}</div>
</body>
</html>