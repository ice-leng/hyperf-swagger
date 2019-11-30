<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Swagger UI 2.0</title>

    <link rel="icon" type="image/png" href="{{link}}/images/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="{{link}}/images/favicon-16x16.png" sizes="16x16" />

<!--    <link href='{{link}}/css/typography.css' media='screen' rel='stylesheet' type='text/css'/>-->
    <link href='{{link}}/css/reset.css' media='screen' rel='stylesheet' type='text/css'/>
    <link href='{{link}}/css/screen.css' media='screen' rel='stylesheet' type='text/css'/>
    <link href='{{link}}/css/print.css' media='print' rel='stylesheet' type='text/css'/>

    <script src='{{link}}/lib/object-assign-pollyfill.js' type='text/javascript'></script>
    <script src='{{link}}/lib/jquery-1.8.0.min.js' type='text/javascript'></script>
    <script src='{{link}}/lib/jquery.slideto.min.js' type='text/javascript'></script>
    <script src='{{link}}/lib/jquery.wiggle.min.js' type='text/javascript'></script>
    <script src='{{link}}/lib/jquery.ba-bbq.min.js' type='text/javascript'></script>
    <script src='{{link}}/lib/handlebars-4.0.5.js' type='text/javascript'></script>
    <script src='{{link}}/lib/lodash.min.js' type='text/javascript'></script>
    <script src='{{link}}/lib/backbone-min.js' type='text/javascript'></script>
    <script src='{{link}}/swagger-ui.js' type='text/javascript'></script>
    <script src='{{link}}/lib/highlight.9.1.0.pack.js' type='text/javascript'></script>
    <script src='{{link}}/lib/highlight.9.1.0.pack_extended.js' type='text/javascript'></script>
    <script src='{{link}}/lib/jsoneditor.min.js' type='text/javascript'></script>
    <script src='{{link}}/lib/marked.js' type='text/javascript'></script>
    <script src='{{link}}/lib/swagger-oauth.js' type='text/javascript'></script>
    <script src='{{link}}/lib/jquery.sieve.js' type='text/javascript'></script>

    <script src='{{link}}/lang/translator.js' type='text/javascript'></script>
    <script src='{{link}}/lang/zh-cn.js' type='text/javascript'></script>

    <script type="text/javascript">
        $(function () {
            var url = window.location.search.match(/url=([^&]+)/);
            if (url && url.length > 1) {
                url = decodeURIComponent(url[1]);
            } else {
                url = "{{url}}";
            }
            hljs.configure({
                highlightSizeThreshold: 5000
            });
            // Pre load translate...
            if(window.SwaggerTranslator) {
                window.SwaggerTranslator.translate();
            }
            window.swaggerUi = new SwaggerUi({
                url: url,
                dom_id: "swagger-ui-container",
                supportedSubmitMethods: ['options', 'head', 'get', 'post', 'put', 'delete', 'patch'],
                onComplete: function(swaggerApi, swaggerUi){
                    if(typeof initOAuth == "function") {
                        initOAuth({{oauthConfig}});
                    }
                    if(window.SwaggerTranslator) {
                        window.SwaggerTranslator.translate();
                    }
                    $("pre code").each(function(i,e){
                        hljs.highlightBlock(e);
                    });
                    var searchTemplate="<div class='filter'><div class='search'><input type='text' placeholder='查询方法 '/></div></div>";
                    $("#swagger-ui-container").find(">div>ul").sieve({
                        itemSelector:"li",
                        searchTemplate:searchTemplate
                    });
                    $(".filter input").on("keypress",function(){
                        $("#resources >li > ul.endpoints").show();
                        //$(".options li").show();
                    })
                },
                onFailure: function(data) {
                    log("Unable to Load SwaggerUI");
                },
                docExpansion: "none",
                //模块排序
                apisSorter:"alpha",
                //模块内部方法排序
                operationsSorter:"method",
                //方法响应排序
                operationResponsesSorter:"sortWeight",
                jsonEditor: false,
                defaultModelRendering: 'schema',
                showRequestHeaders: false,
                showOperationIds: false
            });
            window.swaggerUi.changApiKey=function(value){
                var key=value;
                if(key&&key.trim()!=""){
                    var apiKeyAuth=new SwaggerClient.ApiKeyAuthorization("Authorization",key,"header");
                    window.swaggerUi.api.clientAuthorizations.add("api_key",apiKeyAuth);
                    log("added key "+key);
                }
            }
            function addApiKeyAuthorization(){
                var key=encodeURIComponent($("#input_apiKey")[0].value);
                if(key&&key.trim()!=""){
                    var apiKeyAuth=new SwaggerClient.ApiKeyAuthorization("api_key",key,"query");
                    window.swaggerUi.api.clientAuthorizations.add("api_key",apiKeyAuth);
                    log("added key "+key);
                }
            }
            $("#input_apiKey").change(addApiKeyAuthorization);
            window.swaggerUi.load();
            function log() {
                if ('console' in window) {
                    console.log.apply(console, arguments);
                }
            }
        });
    </script>
</head>

<body class="swagger-section">
<div id='header'>
    <div class="swagger-ui-wrap">
        <a id="logo" href="#"><img class="logo__img" alt="swagger" height="30" width="30" src="{{link}}/images/logo_small.png" /><span class="logo__title">swagger</span></a>
        <form id='api_selector'>
            <div class='input'><input placeholder="http://example.com/api" id="input_baseUrl" name="baseUrl" type="text"/></div>
            <div id='auth_container'></div>
            <div class='input'><a id="explore" class="header__btn" href="#" data-sw-translate>Explore</a></div>
        </form>
    </div>
</div>

<div id="message-bar" class="swagger-ui-wrap" data-sw-translate>&nbsp;</div>
<div id="swagger-ui-container" class="swagger-ui-wrap"></div>
</body>
</html>
