<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Conciliación - Confirmación de Proceso</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 35%, #0369a1 65%, #0f172a 100%);
            min-height: 100vh;
            color: #334155;
            line-height: 1.6;
            font-weight: 400;
            letter-spacing: -0.01em;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Loading State */
        .loading-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .loading-card {
            width: 100%;
            max-width: 400px;
            text-align: center;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 40px 30px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .loading-message {
            color: #64748b;
            font-size: 14px;
        }

        /* Error State */
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .error-card {
            width: 100%;
            max-width: 480px;
            text-align: center;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 40px 30px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid #fecaca;
        }

        .error-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 24px;
            font-weight: 600;
        }

        .error-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .error-message {
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .retry-btn,
        .demo-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            margin: 0 8px;
        }

        .retry-btn:hover,
        .demo-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-1px);
        }

        .demo-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        .demo-btn:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }

        /* Header */
        .header {
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            padding: 24px 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .header-title {
            font-size: 20px;
            font-weight: 600;
            color: white;
            letter-spacing: -0.02em;
        }

        /* Main Content */
        .main { padding: 64px 0; }

        .content-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 64px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
            letter-spacing: -0.01em;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 700;
            color: white;
            margin-bottom: 24px;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .hero-subtitle {
            font-size: 20px;
            color: #cbd5e1;
            margin-bottom: 12px;
            font-weight: 400;
            letter-spacing: -0.01em;
        }

        .hero-description {
            color: #94a3b8;
            font-size: 16px;
            font-weight: 400;
        }

        /* Card Styles */
        .card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(226, 232, 240, 0.8);
            margin-bottom: 32px;
            overflow: hidden;
        }

        .card-header {
            padding: 32px 32px 0;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 32px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.01em;
        }

        .card-icon {
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .card-content { padding: 0 32px 32px; }

        /* Prestador Information */
        .prestador-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .info-field { display: flex; flex-direction: column; }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            font-size: 15px;
            transition: all 0.2s ease;
            min-height: 56px;
            display: flex;
            align-items: center;
        }

        .info-value:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }

        .info-value.nit {
            font-family: 'SF Mono','Monaco','Inconsolata','Roboto Mono', monospace;
            font-size: 16px;
            color: #0f172a;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .info-value.razon-social {
            font-weight: 600;
            color: #0f172a;
            font-size: 16px;
        }

        .info-value.facturas {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #93c5fd;
            text-align: center;
            justify-content: center;
        }

        .info-value.valor {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border-color: #86efac;
            text-align: center;
            justify-content: center;
        }

        .value-number {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .value-number.blue { color: #1e40af; }
        .value-number.green { color: #166534; }

        .value-unit {
            font-size: 12px;
            margin-left: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .value-unit.blue { color: #3b82f6; }
        .value-unit.green { color: #22c55e; }

        /* Summary Bar */
        .summary-bar {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            border-radius: 12px;
            padding: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 14px 0 rgba(30, 64, 175, 0.3);
        }

        .summary-left {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 15px;
        }

        .summary-check {
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .summary-right { text-align: right; }

        .summary-label {
            font-size: 12px;
            color: #bfdbfe;
            margin-bottom: 4px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-value {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Process Steps */
        .process-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }

        .process-card {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            color: white;
            padding: 32px;
            transition: all 0.3s ease;
        }

        .process-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .process-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .process-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .process-card-title { font-size: 16px; font-weight: 600; letter-spacing: -0.01em; }
        .process-card-content { color: #cbd5e1; line-height: 1.6; font-size: 14px; }

        /* Form Styles */
        .form-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .form-header {
            text-align: center;
            padding: 40px 32px 0;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 32px;
        }

        .form-title {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .form-description {
            color: #64748b;
            font-size: 16px;
            font-weight: 400;
        }

        .form-content { padding: 0 32px 40px; }

        .form-group { margin-bottom: 32px; }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .form-control {
            width: 100%;
            padding: 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            line-height: 1.6;
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            background: white;
        }

        .form-textarea {
            width: 100%;
            min-height: 120px;
            padding: 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
            line-height: 1.6;
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            background: white;
        }

        .form-error {
            font-size: 12px;
            margin-top: 8px;
            min-height: 20px;
            color: #dc2626;
        }

        .alert.alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
        }

        /* Confirmation Box */
        .confirmation-box {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .confirmation-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .confirmation-icon {
            width: 20px;
            height: 20px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .confirmation-title {
            font-weight: 600;
            color: #1e40af;
            font-size: 15px;
            letter-spacing: -0.01em;
        }

        .confirmation-list {
            list-style: none;
            color: #1d4ed8;
            font-size: 14px;
            line-height: 1.6;
        }

        .confirmation-list li {
            margin-bottom: 8px;
            padding-left: 24px;
            position: relative;
        }

        .confirmation-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #3b82f6;
            font-weight: 600;
        }

        /* Button */
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            font-weight: 600;
            font-size: 16px;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 4px 14px 0 rgba(59,130,246,0.4);
            transition: all 0.3s ease;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px 0 rgba(59,130,246,0.5);
        }

        .submit-btn:active { transform: translateY(0); }

        .btn-icon {
            width: 16px;
            height: 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        /* Success State */
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .success-card {
            width: 100%;
            max-width: 480px;
            text-align: center;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 48px 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            border: 1px solid rgba(226,232,240,0.8);
        }

        .success-icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 25px 0 rgba(34,197,94,0.3);
        }

        .success-icon { color: white; font-size: 32px; font-weight: 600; }

        .success-title {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .success-message {
            color: #64748b;
            margin-bottom: 32px;
            line-height: 1.6;
            font-size: 16px;
        }

        .back-btn {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Utility Classes */
        .hidden { display: none !important; }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title { font-size: 32px; }
            .prestador-grid { grid-template-columns: 1fr; }
            .summary-bar { flex-direction: column; text-align: center; }
            .summary-right { text-align: center; }
            .card-content, .form-content { padding: 0 24px 32px; }
            .card-header, .form-header { padding: 24px 24px 0; }
            .process-card { padding: 24px; }
            .form-error { font-size: 12px; margin-top: 8px; min-height: 20px; color: #dc2626; }
            .alert.alert-danger { background-color: #fee2e2; color: #dc2626; padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 14px; }
        }
    </style>
</head>

<body>
    <!-- Loading State -->
    <div id="loadingContent" class="loading-container">
        <div class="loading-card">
            <div class="loading-spinner"></div>
            <h2 class="loading-title">Cargando Información</h2>
            <p class="loading-message">Consultando datos del prestador de servicios...</p>
        </div>
    </div>

    <!-- Error State -->
    <div id="errorContent" class="error-container hidden">
        <div class="error-card">
            <div class="error-icon">!</div>
            <h2 class="error-title">Error de Conexión</h2>
            <p class="error-message" id="errorMessage">No se pudo conectar con el servidor. Esto puede deberse a restricciones de CORS o problemas de conectividad.</p>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent" class="hidden">
        <!-- Header -->
        <header class="header">
            <div class="container">
                <div class="header-content">
                    <div class="header-icon">PC</div>
                    <h1 class="header-title">Portal de Conciliación</h1>
                </div>
            </div>
        </header>

        <!-- Main -->
        <main class="main">
            <div class="container">
                <div class="content-wrapper">
                    <!-- Hero Section -->
                    <div class="hero">
                        <div class="hero-badge">
                            <div style="width: 8px; height: 8px; background: #60a5fa; border-radius: 50%;"></div>
                            Proceso de Conciliación
                        </div>
                        <h2 class="hero-title">Confirma la Finalización de tu Proceso</h2>
                        <p class="hero-subtitle">Notifica que has completado la respuesta a la solicitud de conciliación</p>
                        <p class="hero-description">y la carga de toda la documentación requerida</p>
                    </div>

                    <!-- Prestador Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <div class="card-icon">PS</div>
                                Información del Prestador de Servicios de Salud
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="prestador-grid">
                                <div class="info-field">
                                    <label class="info-label">Número de Identificación Tributaria</label>
                                    <div class="info-value nit">{{ $third['nit'] }}</div>
                                </div>
                                <div class="info-field">
                                    <label class="info-label">Razón Social</label>
                                    <div class="info-value razon-social">{{ $third['name'] }}</div>
                                </div>
                                <div class="info-field">
                                    <label class="info-label">Cantidad de Facturas</label>
                                    <div class="info-value facturas">
                                        <span class="value-number blue">{{ $invoices_count }}</span>
                                        <span class="value-number blue">-</span>
                                        <span class="value-unit blue">Facturas</span>
                                    </div>
                                </div>
                                <div class="info-field">
                                    <label class="info-label">Valor Total a Conciliar</label>
                                    <div class="info-value valor">
                                        <span class="value-number green">{{ $sum_value_glosa }}</span>
                                        <span class="value-unit green">COP</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary Bar -->
                            <div class="summary-bar">
                                <div class="summary-left">
                                    <div class="summary-check">✓</div>
                                    <span>Proceso de Conciliación Activo</span>
                                </div>
                                <div class="summary-right">
                                    <div class="summary-label">Total a Conciliar</div>
                                    <div class="summary-value" id="summaryValue">{{ $sum_value_glosa }} COP</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Process Steps -->
                    <div class="process-grid">
                        <div class="process-card">
                            <div class="process-card-header">
                                <div class="process-icon">RC</div>
                                <h4 class="process-card-title">Respuesta Completada</h4>
                            </div>
                            <div class="process-card-content">
                                <p>Has respondido a todos los puntos de la solicitud de conciliación de manera completa y detallada.</p>
                            </div>
                        </div>

                        <div class="process-card">
                            <div class="process-card-header">
                                <div class="process-icon">DC</div>
                                <h4 class="process-card-title">Documentos Cargados</h4>
                            </div>
                            <div class="process-card-content">
                                <p>Toda la documentación de soporte ha sido adjuntada correctamente y está lista para revisión.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Form -->
                    <div class="form-card">
                        <div class="form-header">
                            <h3 class="form-title">Enviar Notificación de Finalización</h3>
                            <p class="form-description">Confirma que has completado todo el proceso requerido para la conciliación</p>
                        </div>
                        <div class="form-content">
                            <span class="hidden" id="reconciliation_notification" data-value="{{ $reconciliation_notification }}"></span>
                            <form id="notificationForm" method="POST" action="{{ route('reconciliationGroup.saveNotification') }}">
                                @csrf
                                <div class="form-group">
                                    <label for="name" class="form-label">Nombre de la persona <span class="form-error">(*)</span></label>
                                    <input id="name" name="name" type="text" class="form-control" placeholder="Nombre de la persona">
                                    <div class="form-error text-danger" id="name-error"></div>
                                </div>
                                <div class="hidden">
                                    <label for="reconciliation_group_id" class="form-label">ID del Grupo de Conciliación</label>
                                    <input id="reconciliation_group_id" name="reconciliation_group_id" type="text" class="form-control" value="{{ $reconciliation_group_id }}" readonly>
                                    <div class="form-error text-danger" id="reconciliation_group_id-error"></div>
                                </div>
                                <div class="form-group">
                                    <label for="emails" class="form-label">Correos de notificación <span class="form-error">(*)</span></label>
                                    <input id="emails" name="emails[]" type="text" class="form-control" placeholder="Correos separados por coma">
                                    <div class="form-error text-danger" id="emails-error"></div>
                                </div>
                                <div class="form-group">
                                    <label for="phones" class="form-label">Teléfonos <span class="form-error">(*)</span></label>
                                    <input id="phones" name="phones[]" type="text" class="form-control" placeholder="Ej: 3001234567, 3159876543"><!-- NEW: placeholder guía -->
                                    <div class="form-error text-danger" id="phones-error"></div>
                                </div>
                                <div class="form-group">
                                    <label for="comments" class="form-label">Comentarios Adicionales <span class="form-error">(*)</span></label>
                                    <textarea id="comments" name="message" class="form-textarea"></textarea>
                                    <div class="form-error text-danger" id="message-error"></div>
                                </div>
                                <div class="confirmation-box">
                                    <div class="confirmation-header">
                                        <div class="confirmation-icon">✓</div>
                                        <p class="confirmation-title">Al enviar esta notificación confirmas que:</p>
                                    </div>
                                    <ul class="confirmation-list">
                                        <li>Has completado la respuesta a la solicitud de conciliación</li>
                                        <li>Has cargado todos los documentos requeridos</li>
                                        <li>El proceso está listo para revisión por parte del equipo</li>
                                        <li>La información proporcionada es veraz y completa</li>
                                    </ul>
                                </div>
                                <button class="submit-btn">
                                    <div class="btn-icon">✓</div>
                                    Confirmar Finalización del Proceso
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Footer Info -->
                    <div class="footer">
                        <p class="footer-text">Una vez enviada la notificación, nuestro equipo será informado automáticamente para proceder con la revisión</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Success State -->
    <div id="successContent" class="success-container hidden">
        <div class="success-card">
            <div class="success-icon-wrapper">
                <div class="success-icon">✓</div>
            </div>
            <h2 class="success-title">Notificación Enviada Exitosamente</h2>
            <p class="success-message">Hemos recibido tu confirmación de finalización del proceso de conciliación. Nuestro equipo procederá con la revisión correspondiente.</p>
        </div>
    </div>

    <!-- Success State Notification -->
    <div id="successNotification" class="success-container hidden">
        <div class="success-card">
            <div class="success-icon-wrapper">
                <div class="success-icon">✓</div>
            </div>
            <h2 class="success-title">Esta notificación ya fue enviada exitosamente</h2>
            <p class="success-message">Hemos recibido la notificación anteriormente.</p>
        </div>
    </div>

    <script>
        // Function to show loading state
        function showLoading() {
            document.getElementById('successNotification').classList.add('hidden');
            document.getElementById('loadingContent').classList.remove('hidden');
            document.getElementById('errorContent').classList.add('hidden');
            document.getElementById('mainContent').classList.add('hidden');
            document.getElementById('successContent').classList.add('hidden');
        }

        // Function to show error state
        function showErrorState() {
            document.getElementById('successNotification').classList.add('hidden');
            document.getElementById('loadingContent').classList.add('hidden');
            document.getElementById('errorContent').classList.remove('hidden');
            document.getElementById('mainContent').classList.add('hidden');
            document.getElementById('successContent').classList.add('hidden');
        }

        // Function to show main content
        function showMainContent() {
            document.getElementById('successNotification').classList.add('hidden');
            document.getElementById('loadingContent').classList.add('hidden');
            document.getElementById('errorContent').classList.add('hidden');
            document.getElementById('mainContent').classList.remove('hidden');
            document.getElementById('successContent').classList.add('hidden');

            // Clear the form
            document.getElementById('comments').value = '';
        }

        // Function to show success content
        function showSuccessContent() {
            document.getElementById('successNotification').classList.add('hidden');
            document.getElementById('loadingContent').classList.add('hidden');
            document.getElementById('errorContent').classList.add('hidden');
            document.getElementById('mainContent').classList.add('hidden');
            document.getElementById('successContent').classList.remove('hidden');
        }

        // Function to show success Notification
        function showSuccessNotification() {
            document.getElementById('loadingContent').classList.add('hidden');
            document.getElementById('errorContent').classList.add('hidden');
            document.getElementById('mainContent').classList.add('hidden');
            document.getElementById('successContent').classList.add('hidden');
            document.getElementById('successNotification').classList.remove('hidden');
        }

        // Function to show errors in the UI
        function showError(errors) {
            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
            document.querySelectorAll('.alert.alert-danger').forEach(el => el.remove());

            // If errors is a string (general message), convert to object with general error
            if (typeof errors === 'string') {
                errors = { general: [errors] };
            }

            // Aggregate email errors (e.g., emails.0, emails.1, etc.)
            const emailErrors = Object.keys(errors)
                .filter(key => key.startsWith('emails.'))
                .flatMap(key => errors[key]);

            // Aggregate phone errors (e.g., phones.0, phones.1, etc.)
            const phoneErrors = Object.keys(errors)
                .filter(key => key.startsWith('phones.'))
                .flatMap(key => errors[key]);

            // Display errors by field
            if (errors.name) {
                document.getElementById('name-error').textContent = errors.name.join('; ');
            }
            if (errors.emails) {
                document.getElementById('emails-error').textContent = errors.emails.join('; ');
            }
            if (emailErrors.length > 0) {
                document.getElementById('emails-error').textContent = emailErrors.join('; ');
            }
            if (errors.phones) {
                document.getElementById('phones-error').textContent = errors.phones.join('; ');
            }
            if (phoneErrors.length > 0) {
                document.getElementById('phones-error').textContent = phoneErrors.join('; ');
            }
            if (errors.message) {
                document.getElementById('message-error').textContent = errors.message.join('; ');
            }
            if (errors.reconciliation_group_id) {
                document.getElementById('reconciliation_group_id-error').textContent = errors.reconciliation_group_id.join('; ');
            }

            // Display general errors at the top of the form
            if (errors.general) {
                const errorContainer = document.createElement('div');
                errorContainer.className = 'alert alert-danger';
                errorContainer.textContent = errors.general.join('; ');
                const formContent = document.querySelector('.form-content') || document.getElementById('notificationForm');
                formContent.prepend(errorContainer);

                // Remove the error message after 5 seconds
                setTimeout(() => errorContainer.remove(), 5000);
            }
        }

        // =========================
        // NEW: Utilidades de parsing/validación (coma como separador)
        // =========================
        function parseCommaList(input) {
            return String(input || '')
                .split(',')
                .map(s => s.trim())
                .filter(Boolean);
        }

        function isValidPhone(phone) {
            return /^\d{10}$/.test(phone); // exactamente 10 dígitos
        }

        function validatePhones(phones) {
            const invalid = phones.filter(p => !isValidPhone(p));
            return { ok: invalid.length === 0, invalid };
        }
        // =========================

        // Form submission handler with AJAX
        document.addEventListener('DOMContentLoaded', function() {
            // Check if notification was already sent
            const reconciliationNotification = document.getElementById('reconciliation_notification')?.dataset.value;
            if (reconciliationNotification === 'true') {
                showSuccessNotification();
            } else {
                showMainContent();
            }

            const form = document.getElementById('notificationForm');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = new FormData(form);

                // ---- EMAILS: aceptar separados por coma
                const emailsInput = formData.get('emails[]') || '';
                formData.delete('emails[]');
                const emails = parseCommaList(emailsInput);
                emails.forEach(email => formData.append('emails[]', email));

                // ---- PHONES: separados por coma y exactamente 10 dígitos (NEW)
                const phonesInput = formData.get('phones[]') || '';
                formData.delete('phones[]');
                let phones = parseCommaList(phonesInput);

                // Limpieza defensiva: dejar solo dígitos por si hay espacios o guiones
                phones = phones.map(p => p.replace(/\D+/g, ''));

                const phoneCheck = validatePhones(phones);
                if (phones.length === 0) {
                    showError({ phones: ['Ingrese al menos un teléfono.'] });
                    return;
                }
                if (!phoneCheck.ok) {
                    showError({
                        phones: [
                            `Los siguientes teléfonos no son válidos (deben tener exactamente 10 dígitos): ${phoneCheck.invalid.join(', ')}`
                        ]
                    });
                    return;
                }
                // Reinyectar al FormData
                phones.forEach(p => formData.append('phones[]', p));

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json'
                        }
                    });

                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        showError('La respuesta del servidor no es JSON');
                        return;
                    }

                    const result = await response.json();

                    if (response.ok) {
                        if (result.already_sent) {
                            showSuccessNotification(); // Ya había sido enviada
                        } else {
                            showSuccessContent(); // Éxito
                        }
                    } else {
                        showError(result.errors || result.message || 'Error al enviar la notificación');
                    }
                } catch (error) {
                    showError('No se pudo conectar con el servidor. Por favor, intenta de nuevo.');
                }
            });
        });
    </script>
</body>
</html>
