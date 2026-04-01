<table>
    <tr>
        <th>Name</th>
        <th>Description</th>
        <th>Status</th>
    </tr>
    @foreach ($regions as $region)
        <tr>
            <td>{{ $region->name }}</td>
            <td>{{ $region->description }}</td>
            <td>{{ $region->status }}</td>
        </tr>
    @endforeach
</table>
