import UIKit
import WebKit
import Capacitor

/// Bridge-controller van de Renovion-app: swipe-terug zoals in Safari, native printen en
/// het offline-scherm (server.errorPath) alleen bij echte verbindingsfouten.
class MainViewController: CAPBridgeViewController {
    private var navigationFilter: NavigationErrorFilter?

    override func capacitorDidLoad() {
        bridge?.registerPluginInstance(RenovionPrintPlugin())

        guard let webView = webView else { return }

        // De app is een server-gerenderde omgeving zonder browserbalk: terug = swipe vanaf de rand.
        webView.allowsBackForwardNavigationGestures = true

        // Geen zoom in de app, zoals een native app: geen dubbeltik- of knijpzoom en geen inzoomen bij het
        // focussen van een invoerveld. In een WKWebView telt user-scalable=no wel (Safari negeert het), dus de
        // website in de browser blijft gewoon zoombaar.
        webView.configuration.userContentController.addUserScript(
            WKUserScript(source: Self.noZoomScript, injectionTime: .atDocumentEnd, forMainFrameOnly: true)
        )

        if let capacitorDelegate = webView.navigationDelegate {
            let filter = NavigationErrorFilter(target: capacitorDelegate)
            navigationFilter = filter
            webView.navigationDelegate = filter
        }
    }

    /// Zet de viewport vast op schaal 1 en zet dubbeltikzoom uit (touch-action). Turbo vervangt bij elke
    /// navigatie de <meta name="viewport"> door die van de nieuwe pagina, dus na turbo:render opnieuw.
    private static let noZoomScript = """
    (function () {
        var content = 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover';
        function lock() {
            var head = document.head || document.documentElement;
            var meta = document.querySelector('meta[name="viewport"]');
            if (!meta) {
                meta = document.createElement('meta');
                meta.name = 'viewport';
                head.appendChild(meta);
            }
            if (meta.getAttribute('content') !== content) meta.setAttribute('content', content);
            if (!document.getElementById('renovion-no-zoom')) {
                var style = document.createElement('style');
                style.id = 'renovion-no-zoom';
                style.textContent = 'html { touch-action: manipulation; }';
                head.appendChild(style);
            }
        }
        lock();
        document.addEventListener('turbo:render', lock);
        document.addEventListener('turbo:load', lock);
    })();
    """
}

/// Capacitor laadt `server.errorPath` bij elke mislukte navigatie, ook als die alleen werd
/// afgebroken (dubbel tikken, een nieuwe navigatie, een tel:-link). Deze proxy houdt die
/// onderbrekingen tegen en geeft al het andere ongewijzigd door aan Capacitors delegate.
final class NavigationErrorFilter: NSObject, WKNavigationDelegate {
    private weak var target: WKNavigationDelegate?

    init(target: WKNavigationDelegate) {
        self.target = target
    }

    override func responds(to aSelector: Selector!) -> Bool {
        return super.responds(to: aSelector) || (target?.responds(to: aSelector) ?? false)
    }

    override func forwardingTarget(for aSelector: Selector!) -> Any? {
        if target?.responds(to: aSelector) == true {
            return target
        }
        return super.forwardingTarget(for: aSelector)
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        guard !Self.isInterruption(error) else { return }
        target?.webView?(webView, didFail: navigation, withError: error)
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        guard !Self.isInterruption(error) else { return }
        target?.webView?(webView, didFailProvisionalNavigation: navigation, withError: error)
    }

    private static func isInterruption(_ error: Error) -> Bool {
        let nsError = error as NSError
        if nsError.domain == NSURLErrorDomain && nsError.code == NSURLErrorCancelled {
            return true
        }
        // WebKitErrorFrameLoadInterruptedByPolicyChange: navigatie overgenomen door het systeem.
        return nsError.domain == "WebKitErrorDomain" && nsError.code == 102
    }
}

/// window.print() doet niets in WKWebView. resources/js/native.js stuurt het hierheen:
/// de iOS-printdialoog, inclusief "Bewaar als PDF" via het deelmenu.
@objc(RenovionPrintPlugin)
public class RenovionPrintPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "RenovionPrintPlugin"
    public let jsName = "RenovionPrint"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "printPage", returnType: CAPPluginReturnPromise)
    ]

    @objc func printPage(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            guard let webView = self.bridge?.webView else {
                call.reject("Geen pagina om te printen")
                return
            }

            let printInfo = UIPrintInfo(dictionary: nil)
            printInfo.outputType = .general
            printInfo.jobName = call.getString("title") ?? "Renovion"

            let controller = UIPrintInteractionController.shared
            controller.printInfo = printInfo
            controller.printFormatter = webView.viewPrintFormatter()

            let completion: UIPrintInteractionController.CompletionHandler = { _, completed, _ in
                call.resolve(["completed": completed])
            }

            if UIDevice.current.userInterfaceIdiom == .pad {
                let anchor = CGRect(x: webView.bounds.midX, y: webView.bounds.midY, width: 1, height: 1)
                controller.present(from: anchor, in: webView, animated: true, completionHandler: completion)
            } else {
                controller.present(animated: true, completionHandler: completion)
            }
        }
    }
}
