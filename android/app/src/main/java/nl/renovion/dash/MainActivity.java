package nl.renovion.dash;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(RenovionPrintPlugin.class);
        super.onCreate(savedInstanceState);
        bridge.setWebViewClient(new RenovionWebViewClient(bridge));
    }
}
