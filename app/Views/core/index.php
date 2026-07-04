<div class="container-fluid">

    <h2 class="mb-4">
        Core Platform
    </h2>

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>Módulos Registrados</strong>

        </div>

        <div class="card-body">

            <table class="table">

                <thead>

                    <tr>

                        <th>Módulo</th>

                        <th>Versão</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach($modules as $module): ?>

                    <tr>

                        <td><?= htmlspecialchars($module['display_name']) ?></td>

                        <td><?= htmlspecialchars($module['version']) ?></td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header">

            <strong>Discovery</strong>

        </div>

        <div class="card-body">

<pre><?php print_r($discovery); ?></pre>

        </div>

    </div>

</div>
