package nl.renovion.dash;

import android.net.Uri;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebViewClient;

/**
 * Capacitor toont server.errorPath (offline.html) bij elke HTTP-fout en elke afgebroken navigatie.
 * Hier alleen bij echte verbindingsfouten, en met de mislukte URL erbij zodat "Opnieuw proberen"
 * terugkeert naar dezelfde pagina. Laravel-foutpagina's (404, 419, 500) blijven gewoon zichtbaar.
 *
 * De WebViewListener-meldingen van de basisklasse vallen hiermee weg; geen van de geïnstalleerde
 * plugins gebruikt die foutcallbacks (bij een nieuwe plugin even nakijken).
 */
public class RenovionWebViewClient extends BridgeWebViewClient {

    private final Bridge bridge;

    public RenovionWebViewClient(Bridge bridge) {
        super(bridge);
        this.bridge = bridge;
    }

    @Override
    public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse errorResponse) {
        // Bewust leeg: de foutpagina van Laravel zelf tonen.
    }

    @Override
    public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
        String errorUrl = bridge.getErrorUrl();
        if (errorUrl == null || !request.isForMainFrame() || isInterruption(error)) {
            return;
        }

        view.loadUrl(errorUrl + "?retry=" + Uri.encode(request.getUrl().toString()));
    }

    private static boolean isInterruption(WebResourceError error) {
        CharSequence description = error.getDescription();
        return description != null && description.toString().contains("ERR_ABORTED");
    }
}
