<?php
/**
 * Dados, helpers e datas (sem valores fixos — os dados vivem no repositório).
 *
 * Fuso horário: usa o do sistema (relógio do utilizador).
 * NÃO fixar 'Europe/Lisbon' aqui — o app desktop deve seguir a hora local da máquina.
 */

$DIAS_SEMANA = [
    'Monday' => 'Segunda', 'Tuesday' => 'Terça', 'Wednesday' => 'Quarta',
    'Thursday' => 'Quinta', 'Friday' => 'Sexta', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo',
];
$MESES_ANO = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
              7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];

/* Utilizador (simulado até existir autenticação) */
$utilizador = ['nome' => 'Kelsen', 'cargo' => 'Administrador'];

/** Dados iniciais de demonstração, usados só na primeira execução */
function seed_tarefas(): array {
    require_once __DIR__ . '/db.php';
    $hoje    = data_local();
    $amanha  = date('Y-m-d', strtotime($hoje . ' +1 day'));
    $ontem   = date('Y-m-d', strtotime($hoje . ' -1 day'));

    return [
        ['id' => 1, 'titulo' => 'Reunião de equipa semanal', 'data' => $hoje, 'hora' => '09:30', 'prioridade' => 'alta',  'estado' => 'pendente',  'cat' => 'Trabalho'],
        ['id' => 2, 'titulo' => 'Enviar relatório mensal',   'data' => $hoje, 'hora' => '12:00', 'prioridade' => 'alta',  'estado' => 'pendente',  'cat' => 'Trabalho'],
        ['id' => 3, 'titulo' => 'Revisar proposta do cliente','data' => $hoje, 'hora' => '15:00', 'prioridade' => 'media', 'estado' => 'pendente',  'cat' => 'Clientes'],
        ['id' => 4, 'titulo' => 'Treino na academia',        'data' => $hoje, 'hora' => '18:30', 'prioridade' => 'baixa', 'estado' => 'concluida', 'cat' => 'Pessoal'],
        ['id' => 5, 'titulo' => 'Pagar fatura de energia',   'data' => $ontem, 'hora' => '23:59', 'prioridade' => 'alta', 'estado' => 'pendente',  'cat' => 'Pessoal'],
        ['id' => 6, 'titulo' => 'Backup semanal dos projetos','data' => $ontem, 'hora' => '22:00', 'prioridade' => 'media', 'estado' => 'concluida', 'cat' => 'Trabalho'],
        ['id' => 7, 'titulo' => 'Escrever artigo no blog',   'data' => $amanha, 'hora' => '10:00', 'prioridade' => 'baixa', 'estado' => 'pendente', 'cat' => 'Pessoal'],
        ['id' => 8, 'titulo' => 'Atualizar dependências do projeto', 'data' => $amanha, 'hora' => '14:00', 'prioridade' => 'media', 'estado' => 'pendente', 'cat' => 'Trabalho'],
        ['id' => 9, 'titulo' => 'Compra do mês',             'data' => $amanha, 'hora' => '17:00', 'prioridade' => 'baixa', 'estado' => 'pendente',  'cat' => 'Pessoal'],
        ['id' => 10, 'titulo' => 'Renovar licenças de software', 'data' => date('Y-m-d', strtotime($hoje . ' +3 days')), 'hora' => '11:00', 'prioridade' => 'media', 'estado' => 'pendente', 'cat' => 'Trabalho'],
        ['id' => 11, 'titulo' => 'Consulta de rotina',       'data' => date('Y-m-d', strtotime($hoje . ' +5 days')), 'hora' => '16:30', 'prioridade' => 'baixa', 'estado' => 'pendente', 'cat' => 'Saúde'],
    ];
}

/* ---------------------------------------------------------------
 * Consultas
 * ------------------------------------------------------------- */

function tarefas_do_dia(array $tarefas, string $dia): array {
    $res = array_values(array_filter($tarefas, fn($t) => $t['data'] === $dia));
    usort($res, fn($a, $b) => $a['hora'] <=> $b['hora']);
    return $res;
}

function estatisticas(array $tarefas): array {
    require_once __DIR__ . '/db.php';
    $hoje = data_local();
    $pendentes = 0; $concluidasHoje = 0; $atrasadas = 0;
    foreach ($tarefas as $t) {
        if ($t['estado'] === 'concluida') {
            if ($t['data'] === $hoje) $concluidasHoje++;
            continue;
        }
        $pendentes++;
        if ($t['data'] < $hoje) $atrasadas++;
    }
    $total = count($tarefas);
    $concluidas = $total - $pendentes;
    return [
        'pendentes'       => $pendentes,
        'concluidas'      => $concluidas,
        'concluidas_hoje' => $concluidasHoje,
        'atrasadas'       => $atrasadas,
        'taxa_conclusao'  => $total > 0 ? round($concluidas / $total * 100) : 0,
        'total'           => $total,
    ];
}

/** Mini-calendário do mês corrente (matriz de semanas) */
function calendario_mes(): array {
    require_once __DIR__ . '/db.php';
    $hojeLocal = data_local();
    [$anoCorrente, $mesAtual] = [intval(substr($hojeLocal, 0, 4)), intval(substr($hojeLocal, 5, 2))];
    $primeiroDia = mktime(0, 0, 0, $mesAtual, 1, $anoCorrente);
    $diasMes = (int)date('t', $primeiroDia);
    $inicioSemana = (int)date('N', $primeiroDia) - 1; // 0 = segunda
    $semanas = [];
    $semana = array_fill(0, $inicioSemana, null);
    for ($d = 1; $d <= $diasMes; $d++) {
        $semana[] = $d;
        if (count($semana) === 7) { $semanas[] = $semana; $semana = []; }
    }
    if ($semana) $semanas[] = array_pad($semana, 7, null);
    return $semanas;
}

/** Valores de prioridade válidos */
function prioridades_validas(): array {
    return ['alta', 'media', 'baixa'];
}

function classe_prioridade(string $p): string {
    return match ($p) { 'alta' => 'alta', 'media' => 'media', default => 'baixa' };
}

function rotulo_prioridade(string $p): string {
    return match ($p) { 'alta' => 'Alta', 'media' => 'Média', default => 'Baixa' };
}
