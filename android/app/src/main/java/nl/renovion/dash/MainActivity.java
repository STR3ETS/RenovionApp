package nl.renovion.dash;

import android.net.Uri;
import android.os.Bundle;
import androidx.webkit.WebViewCompat;
import androidx.webkit.WebViewFeature;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.Logger;
import java.lang.reflect.Method;
import java.util.Collections;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(RenovionPrintPlugin.class);
        super.onCreate(savedInstanceState);
        bridge.setWebViewClient(new RenovionWebViewClient(bridge));
        injectBridgeOnServerPages();
    }

    /**
     * De schermen van de app staan op https://localhost; alles wat daar (nog) niet in zit, opent als
     * serverpagina in dezelfde webview. Capacitor zet zijn JavaScript-brug alleen op het eigen adres, dus
     * zonder dit draait native.js daar niet (geen spraak, terugknop, Nova die opzij gaat, Turbo). Alleen
     * voor ons eigen serveradres uit capacitor.config.ts (plugins.RenovionServer.url), nooit voor andere sites.
     */
    private void injectBridgeOnServerPages() {
        String server = bridge.getConfig().getPluginConfiguration("RenovionServer").getString("url");
        if (server == null || !WebViewFeature.isFeatureSupported(WebViewFeature.DOCUMENT_START_SCRIPT)) {
            return;
        }
        try {
            Uri uri = Uri.parse(server);
            String origin = uri.getScheme() + "://" + uri.getHost() + (uri.getPort() == -1 ? "" : ":" + uri.getPort());
            // getJSInjector is privé in Capacitor; het is hetzelfde script dat Capacitor op localhost zet.
            Method build = Bridge.class.getDeclaredMethod("getJSInjector");
            build.setAccessible(true);
            Object injector = build.invoke(bridge);
            if (injector == null) {
                return;
            }
            Method script = injector.getClass().getDeclaredMethod("getScriptString");
            script.setAccessible(true);
            WebViewCompat.addDocumentStartJavaScript(bridge.getWebView(), (String) script.invoke(injector), Collections.singleton(origin));
        } catch (Exception exception) {
            Logger.error("Renovion: brug op de serverpagina's lukte niet", exception);
        }
    }
}
