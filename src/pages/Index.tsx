import ChatBot from '@/components/ChatBot';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { MessageSquare, Zap, Shield, Globe, Code, Smartphone, Github, ExternalLink } from 'lucide-react';

const Index = () => {
  return (
    <div className="min-h-screen bg-linear-to-br from-slate-50 via-white to-slate-100">
      {/* Hero Section */}
      <section className="relative overflow-hidden">
        {/* Background Pattern */}
        <div className="absolute inset-0 opacity-[0.03]">
          <div className="absolute inset-0" style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23000000' fill-opacity='1'%3E%3Ccircle cx='30' cy='30' r='1'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`
          }} />
        </div>

        <div className="relative px-6 lg:px-8">
          <div className="mx-auto max-w-4xl pt-20 pb-32 sm:pt-24 sm:pb-40">
            <div className="text-center">
              {/* Status Badge */}
              <div className="mb-8 flex justify-center">
                <Badge variant="outline" className="rounded-full px-4 py-2 text-sm font-medium bg-emerald-50 text-emerald-700 border-emerald-200">
                  <div className="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse" />
                  Live Demo - 100% Functional
                </Badge>
              </div>

              {/* Main Heading */}
              <h1 className="text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl lg:text-7xl">
                ChatBot
                <span className="block text-transparent bg-clip-text bg-linear-to-r from-slate-600 to-slate-800 mt-2">
                  Modern
                </span>
              </h1>

              {/* Subtitle */}
              <p className="mt-6 text-lg leading-8 text-slate-600 max-w-2xl mx-auto">
                A full chatbot demo built with React, TypeScript, and modern architecture. Ready for production with automated testing and CI/CD.
              </p>

              {/* CTA Buttons */}
              <div className="mt-10 flex items-center justify-center gap-x-6">
                <Button 
                  size="lg"
                  className="rounded-full bg-slate-900 px-8 py-4 text-base font-semibold text-white shadow-lg hover:bg-slate-800 transition-all duration-200 hover:scale-105"
                  onClick={() => {
                    // Scroll to demo section
                    document.getElementById('demo')?.scrollIntoView({ behavior: 'smooth' });
                  }}
                >
                  Try Demo
                  <MessageSquare className="ml-2 h-5 w-5" />
                </Button>
                <Button 
                  variant="outline" 
                  size="lg"
                  className="rounded-full px-8 py-4 text-base font-semibold border-slate-300 text-slate-700 hover:bg-slate-50 transition-all duration-200"
                  onClick={() => window.open('https://github.com/Miguel-DevOps/chatbot-demo', '_blank')}
                >
                  View Code
                  <Github className="ml-2 h-5 w-5" />
                </Button>
              </div>

              {/* Tech Stack */}
              <div className="mt-16">
                <p className="text-sm font-semibold text-slate-500 mb-6">Built with modern technologies</p>
                <div className="flex flex-wrap justify-center gap-6 opacity-70">
                  {['React 19', 'TypeScript', 'Vite', 'TailwindCSS', 'PHP 8.4', 'Slim Framework'].map((tech) => (
                    <div key={tech} className="text-sm text-slate-600 font-medium">
                      {tech}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-24 sm:py-32 bg-white">
        <div className="mx-auto max-w-7xl px-6 lg:px-8">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
              Key Features
            </h2>
            <p className="mt-4 text-lg leading-8 text-slate-600">
              A modern chatbot with all the features you need for production.
            </p>
          </div>

          <div className="mx-auto mt-16 max-w-6xl">
            <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
              {/* Feature 1 */}
              <Card className="relative hover:shadow-lg transition-all duration-300 border-slate-200">
                <CardHeader className="pb-4">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100">
                      <Zap className="h-6 w-6 text-slate-700" />
                    </div>
                    <CardTitle className="text-lg font-semibold text-slate-900">
                      Ultra Fast
                    </CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-slate-600">
                    Built with Vite and optimized for maximum performance. Instant loading and immediate responses.
                  </CardDescription>
                </CardContent>
              </Card>

              {/* Feature 2 */}
              <Card className="relative hover:shadow-lg transition-all duration-300 border-slate-200">
                <CardHeader className="pb-4">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100">
                      <Shield className="h-6 w-6 text-slate-700" />
                    </div>
                    <CardTitle className="text-lg font-semibold text-slate-900">
                      Secure by Design
                    </CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-slate-600">
                    Rate limiting, strict input validation, and protected environment variables. Production-ready.
                  </CardDescription>
                </CardContent>
              </Card>

              {/* Feature 3 */}
              <Card className="relative hover:shadow-lg transition-all duration-300 border-slate-200">
                <CardHeader className="pb-4">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100">
                      <Globe className="h-6 w-6 text-slate-700" />
                    </div>
                    <CardTitle className="text-lg font-semibold text-slate-900">
                      Multilingual
                    </CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-slate-600">
                    Full support for Spanish and English with react-i18next. Easy to extend to more languages.
                  </CardDescription>
                </CardContent>
              </Card>

              {/* Feature 4 */}
              <Card className="relative hover:shadow-lg transition-all duration-300 border-slate-200">
                <CardHeader className="pb-4">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100">
                      <Code className="h-6 w-6 text-slate-700" />
                    </div>
                    <CardTitle className="text-lg font-semibold text-slate-900">
                      Clean Architecture
                    </CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-slate-600">
                    Backend with Slim Framework and Clean Architecture. Maintainable and scalable code.
                  </CardDescription>
                </CardContent>
              </Card>

              {/* Feature 5 */}
              <Card className="relative hover:shadow-lg transition-all duration-300 border-slate-200">
                <CardHeader className="pb-4">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100">
                      <Smartphone className="h-6 w-6 text-slate-700" />
                    </div>
                    <CardTitle className="text-lg font-semibold text-slate-900">
                      Responsive Design
                    </CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-slate-600">
                    Responsive design that works perfectly on mobile, tablet and desktop. Modern UI with TailwindCSS.
                  </CardDescription>
                </CardContent>
              </Card>

              {/* Feature 6 */}
              <Card className="relative hover:shadow-lg transition-all duration-300 border-slate-200">
                <CardHeader className="pb-4">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100">
                      <MessageSquare className="h-6 w-6 text-slate-700" />
                    </div>
                    <CardTitle className="text-lg font-semibold text-slate-900">
                      Integrated AI
                    </CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-slate-600">
                    Integration with Google Gemini AI. Intelligent responses with a customizable knowledge base.
                  </CardDescription>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </section>

      {/* Demo Section */}
      <section id="demo" className="py-24 sm:py-32 bg-slate-50">
        <div className="mx-auto max-w-7xl px-6 lg:px-8">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
              Try the ChatBot
            </h2>
            <p className="mt-4 text-lg leading-8 text-slate-600">
              Click the floating button to interact with the chatbot. Includes options for chat, WhatsApp, FAQ and calendar.
            </p>
          </div>

          <div className="mt-16">
            <div className="relative mx-auto max-w-4xl">
              {/* Demo Area Visual */}
              <div className="rounded-2xl bg-white shadow-xl border border-slate-200 p-8 lg:p-12">
                <div className="text-center">
                  <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-900 mb-6">
                    <MessageSquare className="w-10 h-10 text-white" />
                  </div>
                  <h3 className="text-2xl font-bold text-slate-900 mb-4">
                    Interactive ChatBot Demo
                  </h3>
                  <p className="text-slate-600 mb-8 max-w-2xl mx-auto">
                    The chatbot appears as a floating button in the bottom-right corner. 
                    Click to open and explore all its features.
                  </p>
                  
                  {/* Features Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-12">
                    <div className="text-left p-4 rounded-lg bg-slate-50">
                      <h4 className="font-semibold text-slate-900 mb-2">💬 Smart Chat</h4>
                      <p className="text-sm text-slate-600">Natural conversation with AI</p>
                    </div>
                    <div className="text-left p-4 rounded-lg bg-slate-50">
                      <h4 className="font-semibold text-slate-900 mb-2">📱 WhatsApp</h4>
                      <p className="text-sm text-slate-600">Direct redirection to WhatsApp</p>
                    </div>
                    <div className="text-left p-4 rounded-lg bg-slate-50">
                      <h4 className="font-semibold text-slate-900 mb-2">❓ FAQ</h4>
                      <p className="text-sm text-slate-600">Frequently asked questions</p>
                    </div>
                    <div className="text-left p-4 rounded-lg bg-slate-50">
                      <h4 className="font-semibold text-slate-900 mb-2">📅 Calendar</h4>
                      <p className="text-sm text-slate-600">Schedule appointments and meetings</p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Arrow pointing to chatbot */}
              <div className="absolute bottom-4 right-4 hidden lg:block">
                <div className="flex items-center space-x-2 text-slate-500 text-sm">
                  <span>Chatbot here →</span>
                  <div className="w-8 h-8 rounded-full border-2 border-dashed border-slate-300 animate-pulse" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Tech Stack Section */}
      <section className="py-24 sm:py-32 bg-white">
        <div className="mx-auto max-w-7xl px-6 lg:px-8">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
              Technology Stack
            </h2>
            <p className="mt-4 text-lg leading-8 text-slate-600">
              Built with top modern technologies to ensure performance and scalability.
            </p>
          </div>

          <div className="mt-16">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              {/* Frontend */}
              <Card className="p-6 border-slate-200">
                <CardHeader className="pb-4">
                  <CardTitle className="text-xl font-bold text-slate-900">Frontend</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {[
                    { name: 'React 19.1.1', desc: 'Modern UI library with Concurrent Features' },
                    { name: 'TypeScript 5.9.2', desc: 'Static typing for better development' },
                    { name: 'Vite 7.1.7', desc: 'Ultra-fast build tool' },
                    { name: 'TailwindCSS 4.1.13', desc: 'Utility-first CSS framework' },
                    { name: 'Radix UI', desc: 'Unstyled accessible components' },
                    { name: 'TanStack Query', desc: 'Advanced server state management' }
                  ].map((tech) => (
                    <div key={tech.name} className="flex items-start space-x-3">
                      <div className="w-2 h-2 bg-slate-400 rounded-full mt-2 shrink-0" />
                      <div>
                        <p className="font-semibold text-slate-900 text-sm">{tech.name}</p>
                        <p className="text-slate-600 text-xs">{tech.desc}</p>
                      </div>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Backend */}
              <Card className="p-6 border-slate-200">
                <CardHeader className="pb-4">
                  <CardTitle className="text-xl font-bold text-slate-900">Backend</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {[
                    { name: 'PHP 8.4', desc: 'Latest version with strict typing' },
                    { name: 'Slim Framework 4', desc: 'Micro-framework PSR-7 compliant' },
                    { name: 'Clean Architecture', desc: 'Clear separation of concerns' },
                    { name: 'PHP-DI', desc: 'Dependency injection container' },
                    { name: 'PHPUnit + Mockery', desc: 'Comprehensive testing framework' },
                    { name: 'SQLite', desc: 'Lightweight database for rate limiting' }
                  ].map((tech) => (
                    <div key={tech.name} className="flex items-start space-x-3">
                      <div className="w-2 h-2 bg-slate-400 rounded-full mt-2 shrink-0" />
                      <div>
                        <p className="font-semibold text-slate-900 text-sm">{tech.name}</p>
                        <p className="text-slate-600 text-xs">{tech.desc}</p>
                      </div>
                    </div>
                  ))}
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-slate-900 text-white py-12">
        <div className="mx-auto max-w-7xl px-6 lg:px-8">
          <div className="flex flex-col md:flex-row justify-between items-center">
            <div className="flex items-center space-x-3 mb-4 md:mb-0">
              <div className="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                <MessageSquare className="w-6 h-6 text-slate-900" />
              </div>
              <div>
                <p className="font-bold text-lg">ChatBot Demo</p>
                <p className="text-sm text-slate-400">Modern • Secure • Scalable</p>
              </div>
            </div>
            
            <div className="flex items-center space-x-6">
              <Button
                variant="outline"
                size="sm"
                className="border-slate-600 text-slate-300 hover:bg-slate-800 hover:text-white"
                onClick={() => window.open('https://github.com/Miguel-DevOps/chatbot-demo', '_blank')}
              >
                <Github className="w-4 h-4 mr-2" />
                GitHub
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="border-slate-600 text-slate-300 hover:bg-slate-800 hover:text-white"
                onClick={() => window.open('https://github.com/Miguel-DevOps/chatbot-demo', '_blank')}
              >
                <ExternalLink className="w-4 h-4 mr-2" />
                Documentation
              </Button>
            </div>
          </div>
          
          <div className="mt-8 pt-8 border-t border-slate-800 text-center text-sm text-slate-400">
            <p>© 2025 ChatBot Demo. Built with ❤️ using modern technologies.</p>
          </div>
        </div>
      </footer>

      {/* ChatBot Widget - Fixed Position */}
      <ChatBot />
    </div>
  );
};

export default Index;
