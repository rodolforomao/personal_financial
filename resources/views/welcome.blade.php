@php
    $brandLogo = file_exists(public_path('financialiq/logo_transparent.png'))
        ? asset('financialiq/logo_transparent.png')
        : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FinancialIQ centraliza fluxo de caixa, conciliação de extratos, alertas e inteligência financeira para decisões mais rápidas.">
    <title>FinancialIQ — Dashboard financeiro inteligente</title>
    @include('partials.brand-head')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc7/dist/css/adminlte.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-dashboard-page">
    <nav class="public-nav navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand public-brand" href="{{ url('/') }}" aria-label="FinancialIQ">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="FinancialIQ" class="public-brand-logo">
                @else
                    <span class="public-brand-mark">FIQ</span>
                    <span class="public-brand-text">Financial<span>IQ</span></span>
                @endif
            </a>

            <div class="d-flex align-items-center gap-2 ms-auto">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light">Abrir dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-sm public-btn-ghost">Entrar</a>
                    @if(Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-sm public-btn-primary">Começar agora</a>
                    @endif
                @endauth
            </div>
        </div>
    </nav>

    <main>
        <section class="public-hero">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <div class="public-eyebrow">
                            <i class="bi bi-stars"></i>
                            Inteligência financeira para operação real
                        </div>
                        <h1 class="public-hero-title">
                            O cockpit financeiro para decidir com caixa, risco e execução na mesma tela.
                        </h1>
                        <p class="public-hero-copy">
                            O FinancialIQ transforma transações, extratos, operações, alertas e IA em um painel claro para líderes que precisam enxergar margem, pendências e próximos passos sem planilhas soltas.
                        </p>
                        <div class="public-hero-actions">
                            @if(Route::has('register'))
                                <a href="{{ route('register') }}" class="btn public-btn-primary btn-lg">
                                    Criar minha conta
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            @endif
                            <a href="#dashboard-preview" class="btn public-btn-ghost btn-lg">Ver prévia</a>
                        </div>
                        <div class="public-trust-row">
                            <span><i class="bi bi-bank2"></i> OFX/CSV</span>
                            <span><i class="bi bi-robot"></i> Insights IA</span>
                            <span><i class="bi bi-shield-check"></i> Dados por workspace</span>
                        </div>
                    </div>

                    <div class="col-lg-6" id="dashboard-preview">
                        <div class="public-dashboard-shell">
                            <div class="public-dashboard-topbar">
                                <div>
                                    <span class="public-window-dot"></span>
                                    <span class="public-window-dot"></span>
                                    <span class="public-window-dot"></span>
                                </div>
                                <span class="public-live-pill">Demo CFO</span>
                            </div>

                            <div class="public-dashboard-card public-dashboard-card-featured">
                                <div>
                                    <span class="public-card-label">Fluxo líquido mensal</span>
                                    <strong>R$ 42.860</strong>
                                    <small>+18,4% vs. mês anterior</small>
                                </div>
                                <span class="public-status-pill public-status-positive">Saudável</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="public-dashboard-card">
                                        <span class="public-card-label">Receitas</span>
                                        <strong>R$ 128k</strong>
                                        <small>83 transações</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="public-dashboard-card">
                                        <span class="public-card-label">Despesas</span>
                                        <strong>R$ 85k</strong>
                                        <small>12 categorias</small>
                                    </div>
                                </div>
                            </div>

                            <div class="public-chart-panel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="public-card-label">Fluxo de caixa</span>
                                        <h2>Últimos 6 meses</h2>
                                    </div>
                                    <span class="public-status-pill">Atualizado hoje</span>
                                </div>
                                <canvas id="public-cashflow-chart" height="150"></canvas>
                            </div>

                            <div class="public-action-list">
                                <div class="public-action-item">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <div>
                                        <strong>3 lançamentos sem categoria</strong>
                                        <span>Impactam relatório por centro de custo</span>
                                    </div>
                                </div>
                                <div class="public-action-item">
                                    <i class="bi bi-check2-circle"></i>
                                    <div>
                                        <strong>94% de conciliação automática</strong>
                                        <span>Extratos prontos para revisão final</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="public-section">
            <div class="container">
                <div class="public-section-heading">
                    <span class="public-eyebrow public-eyebrow-light">Painel misto</span>
                    <h2>Visão executiva com operação acionável.</h2>
                    <p>Do indicador de caixa ao lançamento pendente, cada bloco aponta para uma decisão, correção ou oportunidade.</p>
                </div>

                <div class="row g-4">
                    <div class="col-md-6 col-xl-3">
                        <article class="public-feature-card">
                            <i class="bi bi-speedometer2"></i>
                            <h3>KPIs CFO</h3>
                            <p>Receitas, despesas, fluxo líquido, patrimônio e previsão em uma leitura rápida.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <article class="public-feature-card">
                            <i class="bi bi-bank"></i>
                            <h3>Conciliação</h3>
                            <p>Importe extratos OFX/CSV e acompanhe sugestões, pendências e taxa de acerto.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <article class="public-feature-card">
                            <i class="bi bi-diagram-3"></i>
                            <h3>Operações</h3>
                            <p>Separe lançamentos por operação, unidade, empresa e projeto sem perder o consolidado.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <article class="public-feature-card">
                            <i class="bi bi-lightbulb"></i>
                            <h3>Insights IA</h3>
                            <p>Alertas e análises ajudam a detectar risco, anomalias e oportunidades de melhoria.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="public-section public-section-muted">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-5">
                        <span class="public-eyebrow public-eyebrow-light">Controle ponta a ponta</span>
                        <h2 class="public-section-title">Da importação ao relatório, sem perder rastreabilidade.</h2>
                        <p class="public-section-copy">
                            O FinancialIQ organiza a rotina financeira com filtros por escopo, qualidade dos dados, anexos e ações de saneamento para manter o painel confiável.
                        </p>
                    </div>
                    <div class="col-lg-7">
                        <div class="public-workflow">
                            <div class="public-workflow-step">
                                <span>01</span>
                                <strong>Captura</strong>
                                <p>Extratos, recibos, transações e recorrências entram em uma base única.</p>
                            </div>
                            <div class="public-workflow-step">
                                <span>02</span>
                                <strong>Classificação</strong>
                                <p>Categorias, regras e contexto operacional reduzem retrabalho manual.</p>
                            </div>
                            <div class="public-workflow-step">
                                <span>03</span>
                                <strong>Decisão</strong>
                                <p>KPIs, gráficos, alertas e IA destacam o que exige ação imediata.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="public-cta-section">
            <div class="container">
                <div class="public-cta-card">
                    <div>
                        <span class="public-eyebrow">Pronto para profissionalizar sua rotina financeira?</span>
                        <h2>Comece com um dashboard que conversa com a operação.</h2>
                    </div>
                    <div class="public-cta-actions">
                        @if(Route::has('register'))
                            <a href="{{ route('register') }}" class="btn public-btn-primary btn-lg">Criar conta</a>
                        @endif
                        <a href="{{ route('login') }}" class="btn public-btn-ghost btn-lg">Entrar</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="public-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
            <span>FinancialIQ</span>
            <span>Dashboard financeiro, conciliação e inteligência para decisões melhores.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const canvas = document.getElementById('public-cashflow-chart');

            if (! canvas || typeof Chart === 'undefined') {
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                    datasets: [
                        {
                            label: 'Receitas',
                            data: [86, 92, 101, 118, 122, 128],
                            backgroundColor: 'rgba(246, 163, 26, 0.88)',
                            borderRadius: 10,
                        },
                        {
                            label: 'Despesas',
                            data: [64, 71, 69, 76, 81, 85],
                            backgroundColor: 'rgba(120, 132, 150, 0.78)',
                            borderRadius: 10,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#eadfce',
                                boxWidth: 12,
                                boxHeight: 12,
                                useBorderRadius: true,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `${context.dataset.label}: R$ ${context.parsed.y}k`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#c3b8a5' },
                        },
                        y: {
                            grid: { color: 'rgba(255, 210, 122, 0.13)' },
                            ticks: {
                                color: '#c3b8a5',
                                callback(value) {
                                    return `R$ ${value}k`;
                                },
                            },
                        },
                    },
                },
            });
        })();
    </script>
</body>
</html>
