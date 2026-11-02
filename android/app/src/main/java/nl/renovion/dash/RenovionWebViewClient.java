package nl.renovion.dash;

import android.net.Uri;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebViewClient;
import com.getcapacitor.Logger;
import java.io.IOException;

/**
 * Capacitor toont server.errorPath (offline.html) bij elke HTTP-fout en elke afgebroken navigatie.
 * Hier alleen bij echte verbindingsfouten, en met de mislukte URL erbij zodat de app terugkeert
 * naar dezelfde pagina. Laravel-foutpagina's (404, 419, 500) blijven gewoon zichtbaar.
 *
 * De offline-pagina staat onder het adres van de server maar komt uit de app zelf: alleen op dat
 * adres krijgt een pagina de Capacitor-brug (splash verbergen, statusbalk), en de /up-controle is
 * dan geen cross-origin-verzoek.
 *
 * De WebViewListener-meldingen van de basisklasse vallen hiermee weg; geen van de geïnstalleerde
 * plugins gebruikt die foutcallbacks (bij een nieuwe plugin even nakijken).
 */
public class RenovionWebViewClient extends BridgeWebViewClient {

    private static final String OFFLINE_PREFIX = "/__renovion-offline/";

    private final Bridge bridge;

    public RenovionWebViewClient(Bridge bridge) {
        super(bridge);
        this.bridge = bridge;
    }

    @Override
    public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
        String path = request.getUrl().getPath();
        if (path == null || !path.startsWith(OFFLINE_PREFIX)) {
            return super.shouldInterceptRequest(view, request);
        }

        String file = path.substring(OFFLINE_PREFIX.length());
        String mimeType = switch (file) {
            case "offline.html" -> "text/html";
            case "renovion-logo.svg" -> "image/svg+xml";
            default -> null;
        };
        if (mimeType == null) {
            return null;
        }

        try {
            return new WebResourceResponse(mimeType, "UTF-8", view.getContext().getAssets().open("public/" + file));
        } catch (IOException exception) {
            Logger.error("Offline-bestand ontbreekt: " + file, exception);
            return null;
        }
    }

    @Override
    public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse errorResponse) {
        // Bewust leeg: de foutpagina van Laravel zelf tonen.
    }

    @Override
    public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
        Uri url = request.getUrl();
        boolean isOfflinePage = url.getPath() != null && url.getPath().startsWith(OFFLINE_PREFIX);
        if (bridge.getErrorUrl() == null || !request.isForMainFrame() || isInterruption(error) || isOfflinePage) {
            return;
        }

        // Een mislukt formulier kun je niet opnieuw openen als pagina: dan terug naar de start.
        Uri server = Uri.parse(bridge.getServerUrl() != null ? bridge.getServerUrl() : bridge.getLocalUrl());
        String origin = server.getScheme() + "://" + server.getAuthority();
        String retryUrl = "GET".equals(request.getMethod()) ? url.toString() : origin + "/";
        view.loadUrl(origin + OFFLINE_PREFIX + "offline.html?retry=" + Uri.encode(retryUrl));
    }

    private static boolean isInterruption(WebResourceError error) {
        CharSequence description = error.getDescription();
        return description != null && description.toString().contains("ERR_ABORTED");
    }
}
