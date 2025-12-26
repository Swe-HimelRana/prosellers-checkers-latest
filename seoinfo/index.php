<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Info API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .info-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
        }
        .api-endpoint {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .badge-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="info-card">
            <div class="text-center mb-4">
                <h1 class="display-4 mb-3">🔍 SEO Info API</h1>
                <p class="lead text-muted">Comprehensive SEO Data Management System</p>
            </div>

            <div class="mb-4">
                <h5 class="mb-3">📊 Available Data Points</h5>
                <div class="row g-2">
                    <div class="col-md-6"><span class="badge-custom">Domain Authority</span></div>
                    <div class="col-md-6"><span class="badge-custom">Page Authority</span></div>
                    <div class="col-md-6"><span class="badge-custom">Spam Score</span></div>
                    <div class="col-md-6"><span class="badge-custom">Backlinks</span></div>
                    <div class="col-md-6"><span class="badge-custom">Referring Domains</span></div>
                    <div class="col-md-6"><span class="badge-custom">Domain Age</span></div>
                    <div class="col-md-6"><span class="badge-custom">IP Information</span></div>
                    <div class="col-md-6"><span class="badge-custom">Blacklist Status</span></div>
                </div>
            </div>

            <div class="mb-4">
                <h5 class="mb-3">⚙️ Features</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">✅ MySQL Support</li>
                    <li class="mb-2">✅ API Key Authentication</li>
                    <li class="mb-2">✅ Automatic IP Resolution</li>
                    <li class="mb-2">✅ Bulk Data Management</li>
                </ul>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">Secure • Fast • Reliable</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
