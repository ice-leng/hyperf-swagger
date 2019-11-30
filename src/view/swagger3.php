<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Swagger UI 3.0</title>
    <link rel="stylesheet" type="text/css" href="{{link}}/swagger-ui.css" >
    <link rel="icon" type="image/png" href="{{link}}/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="{{link}}/favicon-16x16.png" sizes="16x16" />
    <style>
        html
        {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }

        *,
        *:before,
        *:after
        {
            box-sizing: inherit;
        }

        body
        {
            margin:0;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="{{link}}/swagger-ui-bundle.js"> </script>
    <script src="{{link}}/swagger-ui-standalone-preset.js"> </script>
    <script type="text/javascript">
        window.onload = function () {
            // Begin Swagger UI call region
            var url = window.location.search.match(/url=([^&]+)/);
            if (url && url.length > 1) {
                url = decodeURIComponent(url[1]);
            } else {
                url = "{{url}}";
            }
            const ui = SwaggerUIBundle({
                url: url,
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                filter: '',
                layout: "StandaloneLayout"
            });
            // End Swagger UI call region

            window.ui = ui;

            ui.initOAuth({{oauthConfig}});
        }
    </script>
</body>
</html>
