<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Карточка студента</title>
    @include('reports.pdf._style')
</head>
<body>
    <div class="header">
        <h1>Карточка студента</h1>
    </div>

    @php($s = $data['student'] ?? [])
    <table class="meta">
        <tr><td>ФИО</td><td>{{ $s['full_name'] ?? '—' }}</td></tr>
        <tr><td>Номер зачётки</td><td>{{ $s['record_book_number'] ?? '—' }}</td></tr>
        <tr><td>Группа</td><td>{{ $s['group']['name'] ?? '—' }}</td></tr>
        <tr><td>Факультет</td><td>{{ $s['faculty']['short_name'] ?? '—' }}</td></tr>
        <tr><td>Период</td><td>{{ $data['period']['name'] ?? '—' }}</td></tr>
        <tr>
            <td>Совокупный рейтинг</td>
            <td><strong>{{ $data['cumulative_rating'] !== null ? number_format($data['cumulative_rating'], 2, ',', ' ') : '—' }}</strong></td>
        </tr>
    </table>

    <h2>Дисциплины</h2>
    <table>
        <thead>
            <tr>
                <th>Дисциплина</th>
                <th>Шифр</th>
                <th class="text-right">Итоговый рейтинг</th>
                <th>Допущен</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['disciplines'] ?? [] as $d)
                <tr>
                    <td>{{ $d['discipline_name'] }}</td>
                    <td>{{ $d['discipline_code'] }}</td>
                    <td class="text-right">{{ number_format($d['final_rating'], 2, ',', ' ') }}</td>
                    <td>{{ $d['is_admitted'] ? 'да' : 'нет' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Модульные рейтинги</h2>
    <table>
        <thead>
            <tr>
                <th>Модуль</th>
                <th class="text-right">Порядковый номер</th>
                <th class="text-right">Модульный рейтинг</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['disciplines'] ?? [] as $d)
                @foreach($d['modules'] ?? [] as $m)
                    <tr>
                        <td>{{ $m['module_name'] }}</td>
                        <td class="text-right">{{ $m['order_number'] }}</td>
                        <td class="text-right">{{ number_format($m['module_rating'], 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">Страница {PAGE_NUM} из {PAGE_COUNT}</div>
</body>
</html>