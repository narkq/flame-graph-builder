<?php
declare(strict_types=1);

set_error_handler(
    function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    },
    E_ALL
);

set_exception_handler(
    function ($exception) {
        reply('500 Internal Server Error', ['error' => (string) $exception]);
        die;
    }
);

function reply(string $status, array $response): void
{
    header("HTTP/1.1 $status");
    header('Vary: Accept');
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        header('Content-Type: text/plain');
        foreach ($response as $k => $v) {
            echo "$k: $v\n";
        }
    }
}

function invalidRequest(string $reason): void
{
    reply('400 Bad Request', ['error' => $reason]);
}

function absPath(string $file): string
{
    return __DIR__.$file;
}

function url(string $file): string
{
    return "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}{$file}";
}

function runPipe(string $command, string $inputFile, string $outputFile, string $dir = '/opt/flamegraph'): void
{
    $pipes = [];
    $spec = [
        ['file', $inputFile, 'r'],
        ['file', $outputFile, 'w'],
        ['pipe', 'w']
    ];

    $process = proc_open($dir.'/'.$command, $spec, $pipes, $dir);
    if (!is_resource($process)) {
        throw new Exception("could not run $command");
    }

    $err = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitcode = proc_close($process);

    if ($exitcode !== 0) {
        throw new Exception("$command failed with exit code $exitcode:\n$err");
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return invalidRequest('POST request expected');
}

if (!isset($_FILES['perfScript'])) {
    return invalidRequest('"perfScript" parameter is required');
}

if ($_FILES['perfScript']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception("error reading uploaded file: {$_FILES['perfScript']['error']}");
}

$reportId = sha1_file($_FILES['perfScript']['tmp_name']);
$reportDir = "/reports/{$reportId}";
$reportPath = function (string $file) use ($reportDir): string {
    return "{$reportDir}/{$file}";
};

if (!is_dir(absPath($reportDir))) {
    mkdir(absPath($reportDir), 0777, true);
}

$perfScriptFile = $reportPath('perf-script.txt');
$perfFoldedFile = $reportPath('perf-folded.txt');
$flameGlaphFile = $reportPath('flame-graph.svg');
$icicleGraphFile = $reportPath('icicle-graph.svg');
$lockFile = absPath($reportPath('in-progress.lock'));
$doneFile = absPath($reportPath('done.lock'));

if (is_file($doneFile)) {
    $status = '200 OK';
} else {
    $lock = fopen($lockFile, 'a+');
    $wouldblock = 0;
    if (!flock($lock, LOCK_EX | LOCK_NB, $wouldblock)) {
        if ($wouldblock) {
            return reply('409 Conflict', ['error' => "Report $reportId is locked: is someone already uploading it?"]);
        } else {
            throw new Exception("Could not lock $lockFile");
        }
    }

    move_uploaded_file($_FILES['perfScript']['tmp_name'], absPath($perfScriptFile));

    runPipe(
        'stackcollapse-perf.pl',
        absPath($perfScriptFile),
        absPath($perfFoldedFile)
    );

    runPipe(
        'flamegraph.pl '.absPath($perfFoldedFile),
        '/dev/null',
        absPath($flameGlaphFile)
    );

    runPipe(
        'flamegraph.pl --reverse --inverted '.absPath($perfFoldedFile),
        '/dev/null',
        absPath($icicleGraphFile)
    );

    touch($doneFile);
    flock($lock, LOCK_UN);
    fclose($lock);
    $status = '201 Created';
}

reply($status, [
    'flameGraph' => url($flameGlaphFile),
    'icicleGraph' => url($icicleGraphFile),
    'perfScript' => url($perfScriptFile),
    'perfFolded' => url($perfFoldedFile),
    'reportId' => $reportId,
]);
