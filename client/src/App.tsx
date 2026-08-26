import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import Home from "./pages/Home";
import Marketplace from "./pages/Marketplace";
import { ServicesOverview, StoragePage, TransportPage } from "./pages/Services";
import MarketPrices from "./pages/MarketPrices";
import Information from "./pages/Information";
import Workspace from "./pages/Workspace";
import ListingDetail from "./pages/ListingDetail";
import AdminManagement from "./pages/AdminManagement";
import SignIn from "./pages/SignIn";
import SignUp from "./pages/SignUp";
import Recover from "./pages/Recover";
import Profile from "./pages/Profile";
import Notifications from "./pages/Notifications";


function Router() {
  return (
    <Switch>
      <Route path={"/"} component={Home} />
      <Route path={"/marketplace/:id"} component={ListingDetail} />
      <Route path={"/marketplace"} component={Marketplace} />
      <Route path={"/services"} component={ServicesOverview} />
      <Route path={"/storage"} component={StoragePage} />
      <Route path={"/transport"} component={TransportPage} />
      <Route path={"/market-prices"} component={MarketPrices} />
      <Route path={"/sign-in"} component={SignIn} />
      <Route path={"/sign-up"} component={SignUp} />
      <Route path={"/recover"} component={Recover} />
      <Route path={"/profile"} component={Profile} />
      <Route path={"/notifications"} component={Notifications} />
      <Route path={"/about"} component={() => <Information page="about" />} />
      <Route path={"/how-it-works"} component={() => <Information page="how" />} />
      <Route path={"/contact"} component={() => <Information page="contact" />} />
      <Route path={"/farmer"} component={() => <Workspace role="farmer" />} />
      <Route path={"/buyer"} component={() => <Workspace role="buyer" />} />
      <Route path={"/storage-provider"} component={() => <Workspace role="storage" />} />
      <Route path={"/transport-provider"} component={() => <Workspace role="transport" />} />
      <Route path={"/admin"} component={AdminManagement} />
      <Route path={"/404"} component={NotFound} />
      {/* Final fallback route */}
      <Route component={NotFound} />
    </Switch>
  );
}

// NOTE: About Theme
// - First choose a default theme according to your design style (dark or light bg), than change color palette in index.css
//   to keep consistent foreground/background color across components
// - If you want to make theme switchable, pass `switchable` ThemeProvider and use `useTheme` hook

function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider
        defaultTheme="light"
        // switchable
      >
        <TooltipProvider>
          <Toaster />
          <Router />
        </TooltipProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}

export default App;
