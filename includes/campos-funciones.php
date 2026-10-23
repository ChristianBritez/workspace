<?php /* Usa: $f = ['sala' => id, 'horarios' => [...], 'dias' => [...]] */ ?>
<label>Sala
    <select name="sala">
        <?php foreach (salas() as $sid => $s): ?>
            <option value="<?= $sid ?>" <?= $f['sala'] === $sid ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['nombre'] . ' · ' . $s['tipo']) ?> —
                <?= $s['filas'] * $s['columnas'] ?> asientos — <?= plata($s['precio']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<fieldset class="chips">
    <legend>Horarios de función</legend>
    <?php foreach (SLOTS as $h): ?>
        <label class="chip">
            <input type="checkbox" name="horarios[]" value="<?= $h ?>" <?= in_array($h, $f['horarios'], true) ? 'checked' : '' ?>>
            <span><?= $h ?></span>
        </label>
    <?php endforeach; ?>
</fieldset>

<fieldset class="chips">
    <legend>Días en cartelera</legend>
    <?php foreach (ORDEN_DIAS as $d): ?>
        <label class="chip">
            <input type="checkbox" name="dias[]" value="<?= $d ?>" <?= in_array($d, $f['dias'], true) ? 'checked' : '' ?>>
            <span><?= DIAS_CORTOS[$d] ?></span>
        </label>
    <?php endforeach; ?>
</fieldset>
