package nl.renovion.dash;

import android.content.Context;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * window.print() doet niets in de Android-WebView. resources/js/native.js stuurt het hierheen:
 * de Android-printdialoog, inclusief "Opslaan als PDF".
 */
@CapacitorPlugin(name = "RenovionPrint")
public class RenovionPrintPlugin extends Plugin {

    @PluginMethod
    public void printPage(PluginCall call) {
        String title = call.getString("title", "Renovion");

        getActivity().runOnUiThread(() -> {
            PrintManager printManager = (PrintManager) getActivity().getSystemService(Context.PRINT_SERVICE);
            PrintDocumentAdapter adapter = getBridge().getWebView().createPrintDocumentAdapter(title);
            printManager.print(title, adapter, new PrintAttributes.Builder().build());
            call.resolve();
        });
    }
}
