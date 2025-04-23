<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Teacher Dashboard - Stranger Strings</title>
  <link href="src/output.css" rel="stylesheet" />
</head>
<body class="bg-gradient-to-br from-black via-red-900 to-black min-h-screen text-white font-[Cinzel] p-8">

  <?php
    $conn = new mysqli("localhost", "root", "", "stranger_strings");
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
    $result = $conn->query("SELECT * FROM students");
  ?>

  <header class="flex justify-between items-center mb-10">
    <img src="imagefolder/teacher-title.png" alt="Stranger Strings" class="h-16">
    <div class="flex space-x-4">
      <select class="bg-black text-white border border-red-700 px-4 py-2 rounded">
        <option>All Subjects</option>
        <option>AI</option>
        <option>Web Dev</option>
      </select>
      <select class="bg-black text-white border border-red-700 px-4 py-2 rounded">
        <option>All Status</option>
        <option>Completed</option>
        <option>In Progress</option>
        <option>Not Started</option>
      </select>
    </div>
  </header>

  <main class="grid grid-cols-4 gap-8">

    <section class="col-span-3">
      <table class="w-full table-auto text-sm">
        <thead class="text-red-400 border-b border-red-800">
          <tr>
            <th class="p-3 text-left">Name</th>
            <th>Reg No</th>
            <th>Section</th>
            <th>Progress</th>
            <th>Rewards</th>
            <th>Extension</th>
            <th>Feedback</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-red-800">
          <?php while($row = $result->fetch_assoc()): ?>
          <tr class="hover:bg-red-900/40 transition">
            <td class="p-3 font-semibold"> <?= htmlspecialchars($row['name']) ?> </td>
            <td> <?= $row['reg_no'] ?> </td>
            <td> <?= $row['section'] ?> </td>
            <td>
              <div class="w-full bg-red-900 rounded">
                <div class="bg-red-600 h-2 rounded" style="width: <?= $row['progress'] ?>%;"></div>
              </div>
            </td>
            <td class="text-red-300 font-bold"> <?= $row['rewards'] ?> </td>
            <td>
              <?php
                if ($row['extension_request'] === 'Requested') {
                  echo '<span class="bg-yellow-400 text-black text-xs px-2 py-1 rounded-full animate-pulse">Requested</span>';
                } elseif ($row['extension_request'] === 'Granted') {
                  echo '<span class="bg-green-500 text-black text-xs px-2 py-1 rounded-full">Granted</span>';
                } else {
                  echo '<span class="bg-gray-600 text-white text-xs px-2 py-1 rounded-full">None</span>';
                }
              ?>
            </td>
            <td>
              <button class="text-blue-300 hover:underline">Give</button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>

    <aside class="bg-black bg-opacity-60 border border-red-800 p-6 rounded-xl">
      <h2 class="text-xl font-bold mb-4">Project Stats</h2>
      <div class="space-y-3">
        <p class="text-red-300">Avg. Submissions: <span class="font-bold">23</span> / 40</p>
        <p class="text-red-300">Students Granted Extension: <span class="font-bold">5</span></p>
        <p class="text-red-300">Assistance Requests: <span class="font-bold">3</span></p>
      </div>

      <div class="mt-6">
        <h3 class="text-lg font-semibold mb-2">Leaderboard</h3>
        <ul class="space-y-1 text-sm">
          <?php
            $top = $conn->query("SELECT name, rewards FROM students WHERE rewards > 500 AND progress >= 50 ORDER BY rewards DESC LIMIT 5");
            while($s = $top->fetch_assoc()):
          ?>
            <li class="flex justify-between">
              <span><?= htmlspecialchars($s['name']) ?></span>
              <span class="text-red-300 font-semibold"><?= $s['rewards'] ?></span>
            </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </aside>

  </main>

  <?php $conn->close(); ?>

</body>
</html>
